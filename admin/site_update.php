<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Phyxo\LocalSiteReader;
use App\Repository\SiteRepository;
use App\Repository\CategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\GroupAccessRepository;
use App\Repository\UserAccessRepository;
use App\Repository\CaddieRepository;
use App\Repository\BaseRepository;

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

if (!$conf['enable_synchronization']) {
    die('synchronization is disabled');
}

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

if (!is_numeric($_GET['site'])) {
    die('site param missing or invalid');
}
$site_id = $_GET['site'];

$result = (new SiteRepository($conn))->findById($site_id);
list($site_url) = $conn->db_fetch_row($result);
if (!isset($site_url)) {
    die('site ' . $site_id . ' does not exist');
}
$site_is_remote = \Phyxo\Functions\URL::url_is_remote($site_url);

define('CURRENT_DATE', (new BaseRepository($conn))->getNow());

$error_labels = [
    'PWG-UPDATE-1' => [
        \Phyxo\Functions\Language::l10n('wrong filename'),
        \Phyxo\Functions\Language::l10n('The name of directories and files must be composed of letters, numbers, "-", "_" or "."')
    ],
    'PWG-ERROR-NO-FS' => [
        \Phyxo\Functions\Language::l10n('File/directory read error'),
        \Phyxo\Functions\Language::l10n('The file or directory cannot be accessed (either it does not exist or the access is denied)')
    ],
];
$errors = [];
$infos = [];

if ($site_is_remote) {
    \Phyxo\Functions\HTTP::fatal_error('remote sites not supported');
} else {
    $site_reader = new LocalSiteReader(__DIR__ . '/../' . $site_url);
}

$general_failure = true;
if (isset($_POST['submit'])) {
    if ($site_reader->open()) {
        $general_failure = false;
    }

    // shall we simulate only
    if (isset($_POST['simulate']) and $_POST['simulate'] == 1) {
        $simulate = true;
    } else {
        $simulate = false;
    }
}

// +-----------------------------------------------------------------------+
// |                      directories / categories                         |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) and ($_POST['sync'] == 'dirs' or $_POST['sync'] == 'files')) {
    $counts['new_categories'] = 0;
    $counts['del_categories'] = 0;
    $counts['del_elements'] = 0;
    $counts['new_elements'] = 0;
    $counts['upd_elements'] = 0;
}

if (isset($_POST['submit']) and ($_POST['sync'] == 'dirs' or $_POST['sync'] == 'files') and !$general_failure) {
    $start = microtime(true);
    // which categories to update ?
    $result = (new CategoryRepository($conn))->findPhysicalsBySite($site_id, $_POST['cat'], (isset($_POST['subcats-included']) and $_POST['subcats-included'] == 1));
    $db_categories = $conn->result2array($result, 'id');

    // get categort full directories in an array for comparison with file
    // system directory tree
    $db_fulldirs = \Phyxo\Functions\Category::get_fulldirs(array_keys($db_categories));

    // what is the base directory to search file system sub-directories ?
    if (isset($_POST['cat']) and is_numeric($_POST['cat'])) {
        $basedir = $db_fulldirs[$_POST['cat']];
    } else {
        $basedir = preg_replace('#/*$#', '', $site_url);
    }
    $basedir = __DIR__ . '/../' . $basedir;

    // we need to have fulldirs as keys to make efficient comparison
    $db_fulldirs = array_flip($db_fulldirs);

    // finding next rank for each id_uppercat. By default, each category id
    // has 1 for next rank on its sub-categories to create
    $next_rank['NULL'] = 1;

    $result = (new CategoryRepository($conn))->findAll();
    while ($row = $conn->db_fetch_assoc($result)) {
        $next_rank[$row['id']] = 1;
    }

    // let's see if some categories already have some sub-categories...
    $result = (new CategoryRepository($conn))->findWithSubCategories();
    while ($row = $conn->db_fetch_assoc($result)) {
        // for the id_uppercat NULL, we write 'NULL' and not the empty string
        if (!isset($row['id_uppercat']) or $row['id_uppercat'] == '') {
            $row['id_uppercat'] = 'NULL';
        }
        $next_rank[$row['id_uppercat']] = $row['next_rank'];
    }

    // next category id available
    $next_id = (new CategoryRepository($conn))->getNextId();

    // retrieve sub-directories fulldirs from the site reader
    $fs_fulldirs = $site_reader->get_full_directories($basedir);

    // get_full_directories doesn't include the base directory, so if it's a
    // category directory, we need to include it in our array
    if (isset($_POST['cat'])) {
        $fs_fulldirs[] = $basedir;
    }

    // If $_POST['subcats-included'] != 1 ("Search in sub-albums" is unchecked)
    // $db_fulldirs doesn't include any subdirectories and $fs_fulldirs does
    // So $fs_fulldirs will be limited to the selected basedir
    // (if that one is in $fs_fulldirs)
    if (!isset($_POST['subcats-included']) or $_POST['subcats-included'] != 1) {
        $fs_fulldirs = array_intersect($fs_fulldirs, array_keys($db_fulldirs));
    }
    $inserts = [];
    // new categories are the directories not present yet in the database
    foreach (array_diff($fs_fulldirs, array_keys($db_fulldirs)) as $fulldir) {
        $dir = basename($fulldir);
        if (preg_match($conf['sync_chars_regex'], $dir)) {
            $insert = [
                'id' => $next_id++,
                'dir' => $dir,
                'name' => str_replace('_', ' ', $dir),
                'site_id' => $site_id,
                'commentable' => $conn->boolean_to_string($conf['newcat_default_commentable']),
                'status' => $conf['newcat_default_status'],
                'visible' => $conn->boolean_to_string($conf['newcat_default_visible']),
            ];

            if (isset($db_fulldirs[dirname($fulldir)])) {
                $parent = $db_fulldirs[dirname($fulldir)];

                $insert['id_uppercat'] = $parent;
                $insert['uppercats'] = $db_categories[$parent]['uppercats'] . ',' . $insert['id'];
                $insert['rank'] = $next_rank[$parent]++;
                $insert['global_rank'] = $db_categories[$parent]['global_rank'] . '.' . $insert['rank'];
                if ('private' == $db_categories[$parent]['status']) {
                    $insert['status'] = 'private';
                }
                if ('false' == $db_categories[$parent]['visible']) {
                    $insert['visible'] = 'false';
                }
            } else {
                $insert['uppercats'] = $insert['id'];
                $insert {
                    'rank'} = $next_rank['NULL']++;
                $insert['global_rank'] = $insert['rank'];
            }

            $inserts[] = $insert;
            $infos[] = [
                'path' => $fulldir,
                'info' => \Phyxo\Functions\Language::l10n('added'),
            ];

            // add the new category to $db_categories and $db_fulldirs array
            $db_categories[$insert {
                'id'}] =
                [
                    'id' => $insert['id'],
                    'parent' => (isset($parent)) ? $parent : null,
                    'status' => $insert['status'],
                    'visible' => $insert['visible'],
                    'uppercats' => $insert['uppercats'],
                    'global_rank' => $insert['global_rank']
                ];
            $db_fulldirs[$fulldir] = $insert['id'];
            $next_rank[$insert {
                'id'}] = 1;
        } else {
            $errors[] = [
                'path' => $fulldir,
                'type' => 'PWG-UPDATE-1'
            ];
        }
    }

    if (count($inserts) > 0) {
        if (!$simulate) {
            $dbfields = [
                'id', 'dir', 'name', 'site_id', 'id_uppercat', 'uppercats', 'commentable',
                'visible', 'status', 'rank', 'global_rank'
            ];
            (new CategoryRepository($conn))->addCategories($dbfields, $inserts);

            // add default permissions to categories
            $category_ids = [];
            $category_up = [];
            foreach ($inserts as $category) {
                $category_ids[] = $category['id'];
                if (!empty($category['id_uppercat'])) {
                    $category_up[] = $category['id_uppercat'];
                }
            }
            $category_up = implode(',', array_unique($category_up));
            if ($conf['inheritance_by_default']) {
                $result = (new GroupAccessRepository($conn))->findByCatIds($category_up);
                if (!empty($result)) {
                    $granted_grps = [];
                    while ($row = $conn->db_fetch_assoc($result)) {
                        if (!isset($granted_grps[$row['cat_id']])) {
                            $granted_grps[$row['cat_id']] = [];
                        }
                        // TODO: explanation
                        array_push(
                            $granted_grps,
                            [
                                $row['cat_id'] => array_push($granted_grps[$row['cat_id']], $row['group_id'])
                            ]
                        );
                    }
                }
                $result = (new UserAccessRepository($conn))->findByCatIds($category_up);
                if (!empty($result)) {
                    $granted_users = [];
                    while ($row = $conn->db_fetch_assoc($result)) {
                        if (!isset($granted_users[$row['cat_id']])) {
                            $granted_users[$row['cat_id']] = [];
                        }
                        // TODO: explanation
                        array_push(
                            $granted_users,
                            [
                                $row['cat_id'] => array_push($granted_users[$row['cat_id']], $row['user_id'])
                            ]
                        );
                    }
                }
                $insert_granted_users = [];
                $insert_granted_grps = [];
                foreach ($category_ids as $ids) {
                    $parent_id = $db_categories[$ids]['parent'];
                    while (in_array($parent_id, $category_ids)) {
                        $parent_id = $db_categories[$parent_id]['parent'];
                    }
                    if ($db_categories[$ids]['status'] == 'private' and !is_null($parent_id)) {
                        if (isset($granted_grps[$parent_id])) {
                            foreach ($granted_grps[$parent_id] as $granted_grp) {
                                $insert_granted_grps[] = [
                                    'group_id' => $granted_grp,
                                    'cat_id' => $ids
                                ];
                            }
                        }
                        if (isset($granted_users[$parent_id])) {
                            foreach ($granted_users[$parent_id] as $granted_user) {
                                $insert_granted_users[] = [
                                    'user_id' => $granted_user,
                                    'cat_id' => $ids
                                ];
                            }
                        }
                        foreach (\Phyxo\Functions\Utils::get_admins() as $granted_user) {
                            $insert_granted_users[] = [
                                'user_id' => $granted_user,
                                'cat_id' => $ids
                            ];
                        }
                    }
                }

                (new GroupAccessRepository($conn))->massInserts(['group_id', 'cat_id'], $insert_granted_grps);
                $insert_granted_users = array_unique($insert_granted_users, SORT_REGULAR);
                (new UserAccessRepository($conn))->massInserts(['user_id', 'cat_id'], $insert_granted_users);
            } else {
                \Phyxo\Functions\Category::add_permission_on_category($category_ids, \Phyxo\Functions\Utils::get_admins());
            }
        }

        $counts['new_categories'] = count($inserts);
    }

    // to delete categories
    $to_delete = [];
    $to_delete_derivative_dirs = [];

    foreach (array_diff(array_keys($db_fulldirs), $fs_fulldirs) as $fulldir) {
        $to_delete[] = $db_fulldirs[$fulldir];
        unset($db_fulldirs[$fulldir]);

        $infos[] = [
            'path' => $fulldir,
            'info' => \Phyxo\Functions\Language::l10n('deleted')
        ];

        if (substr_compare($fulldir, '../', 0, 3) == 0) {
            $fulldir = substr($fulldir, 3);
        }
        $to_delete_derivative_dirs[] = __DIR__ . '/../' . PWG_DERIVATIVE_DIR . $fulldir;
    }

    if (count($to_delete) > 0) {
        if (!$simulate) {
            \Phyxo\Functions\Category::delete_categories($to_delete);
            foreach ($to_delete_derivative_dirs as $to_delete_dir) {
                if (is_dir($to_delete_dir)) {
                    \Phyxo\Functions\Utils::clear_derivative_cache_rec($to_delete_dir, '#.+#');
                }
            }
        }
        $counts['del_categories'] = count($to_delete);
    }

    $template->append('footer_elements', '<!-- scanning dirs : ' . \Phyxo\Functions\Utils::get_elapsed_time($start, microtime(true)) . ' -->');
}

// +-----------------------------------------------------------------------+
// |                           files / elements                            |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) and $_POST['sync'] == 'files' and !$general_failure) {
    $start_files = microtime(true);
    $start = $start_files;

    $fs = $site_reader->get_elements($basedir);
    $template->append('footer_elements', '<!-- get_elements: ' . \Phyxo\Functions\Utils::get_elapsed_time($start, microtime(true)) . ' -->');

    $cat_ids = array_diff(array_keys($db_categories), $to_delete);

    $db_elements = [];

    if (count($cat_ids) > 0) {
        $result = (new ImageRepository($conn))->findByFields('storage_category_id', $cat_ids);
        $db_elements = $conn->result2array($result, 'id', 'path');
    }

    // next element id available
    $next_element_id = (new ImageRepository($conn))->getNextId();
    $start = microtime(true);

    $inserts = [];
    $insert_links = [];

    foreach (array_diff(array_keys($fs), $db_elements) as $path) {
        $insert = [];
        // storage category must exist
        $dirname = dirname($path);
        if (!isset($db_fulldirs[$dirname])) {
            continue;
        }
        $filename = basename($path);
        if (!preg_match($conf['sync_chars_regex'], $filename)) {
            $errors[] = [
                'path' => $path,
                'type' => 'PWG-UPDATE-1'
            ];

            continue;
        }

        $insert = [
            'id' => $next_element_id++,
            'file' => $filename,
            'name' => \Phyxo\Functions\Utils::get_name_from_file($filename),
            'date_available' => CURRENT_DATE,
            'path' => $path,
            'representative_ext' => $fs[$path]['representative_ext'],
            'storage_category_id' => $db_fulldirs[$dirname],
            'added_by' => $user['id'],
        ];

        if ($_POST['privacy_level'] != 0) {
            $insert['level'] = $_POST['privacy_level'];
        }

        $inserts[] = $insert;

        $insert_links[] = [
            'image_id' => $insert['id'],
            'category_id' => $insert['storage_category_id'],
        ];

        $infos[] = [
            'path' => $insert['path'],
            'info' => \Phyxo\Functions\Language::l10n('added')
        ];

        $caddiables[] = $insert['id'];
    }

    if (count($inserts) > 0) {
        if (!$simulate) {
            // inserts all new elements
            (new ImageRepository($conn))->addImages(array_keys($inserts[0]), $inserts);

            // inserts all links between new elements and their storage category
            (new ImageCategoryRepository($conn))->insertImageCategories(array_keys($insert_links[0]), $insert_links);

            // add new photos to caddie
            if (isset($_POST['add_to_caddie']) and $_POST['add_to_caddie'] == 1) {
                (new CaddieRepository($conn))->fillCaddie($user['id'], $caddiables);
            }
        }
        $counts['new_elements'] = count($inserts);
    }

    // delete elements that are in database but not in the filesystem
    $to_delete_elements = [];
    foreach (array_diff($db_elements, array_keys($fs)) as $path) {
        $to_delete_elements[] = array_search($path, $db_elements);
        $infos[] = [
            'path' => $path,
            'info' => \Phyxo\Functions\Language::l10n('deleted')
        ];
    }
    if (count($to_delete_elements) > 0) {
        if (!$simulate) {
            \Phyxo\Functions\Utils::delete_elements($to_delete_elements);
        }
        $counts['del_elements'] = count($to_delete_elements);
    }

    $template->append('footer_elements', '<!-- scanning files : ' . \Phyxo\Functions\Utils::get_elapsed_time($start_files, microtime(true)) . ' -->');
}

// +-----------------------------------------------------------------------+
// |                          synchronize files                            |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) and ($_POST['sync'] == 'dirs' or $_POST['sync'] == 'files') and !$general_failure) {
    if (!$simulate) {
        $start = microtime(true);
        \Phyxo\Functions\Category::update_category('all');
        $template->append('footer_elements', '<!-- update_category(all) : ' . \Phyxo\Functions\Utils::get_elapsed_time($start, microtime(true)) . ' -->');
        $start = microtime(true);
        \Phyxo\Functions\Utils::update_global_rank();
        $template->append('footer_elements', '<!-- ordering categories : ' . \Phyxo\Functions\Utils::get_elapsed_time($start, microtime(true)) . ' -->');
    }

    if ($_POST['sync'] == 'files') {
        $start = microtime(true);
        $opts['category_id'] = '';
        $opts['recursive'] = true;
        if (isset($_POST['cat'])) {
            $opts['category_id'] = $_POST['cat'];
            if (!isset($_POST['subcats-included']) or $_POST['subcats-included'] != 1) {
                $opts['recursive'] = false;
            }
        }
        $files = \Phyxo\Functions\Utils::get_filelist($opts['category_id'], $site_id, $opts['recursive'], false);
        $template->append('footer_elements', '<!-- get_filelist : ' . \Phyxo\Functions\Utils::get_elapsed_time($start, microtime(true)) . ' -->');
        $start = microtime(true);

        $datas = [];
        foreach ($files as $id => $file) {
            $file = $file['path'];
            $data = $site_reader->get_element_update_attributes($file);
            if (!is_array($data)) {
                continue;
            }

            $data['id'] = $id;
            $datas[] = $data;
        } // end foreach file

        $counts['upd_elements'] = count($datas);
        if (!$simulate and count($datas) > 0) {
            (new ImageRepository($conn))->massUpdates(
                [
                    'primary' => ['id'],
                    'update' => $site_reader->get_update_attributes(),
                ],
                $datas
            );
        }
        $template->append('footer_elements', '<!-- update files : ' . \Phyxo\Functions\Utils::get_elapsed_time($start, microtime(true)) . ' -->');
    }// end if sync files
}

// +-----------------------------------------------------------------------+
// |                          synchronize files                            |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) and ($_POST['sync'] == 'dirs' or $_POST['sync'] == 'files')) {
    $template->assign(
        'update_result',
        [
            'NB_NEW_CATEGORIES' => $counts['new_categories'],
            'NB_DEL_CATEGORIES' => $counts['del_categories'],
            'NB_NEW_ELEMENTS' => $counts['new_elements'],
            'NB_DEL_ELEMENTS' => $counts['del_elements'],
            'NB_UPD_ELEMENTS' => $counts['upd_elements'],
            'NB_ERRORS' => count($errors),
        ]
    );
}

// +-----------------------------------------------------------------------+
// |                          synchronize metadata                         |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) and isset($_POST['sync_meta']) and !$general_failure) {
    // sync only never synchronized files ?
    $opts['only_new'] = isset($_POST['meta_all']) ? false : true;
    $opts['category_id'] = '';
    $opts['recursive'] = true;

    if (isset($_POST['cat'])) {
        $opts['category_id'] = $_POST['cat'];
        // recursive ?
        if (!isset($_POST['subcats-included']) or $_POST['subcats-included'] != 1) {
            $opts['recursive'] = false;
        }
    }
    $start = microtime(true);
    $files = \Phyxo\Functions\Utils::get_filelist(
        $opts['category_id'],
        $site_id,
        $opts['recursive'],
        $opts['only_new']
    );

    $template->append('footer_elements', '<!-- get_filelist : ' . \Phyxo\Functions\Utils::get_elapsed_time($start, microtime(true)) . ' -->');

    $start = microtime(true);
    $datas = [];
    $tags_of = [];

    foreach ($files as $id => $element_infos) {
        $data = $site_reader->get_element_metadata($element_infos);

        if (is_array($data)) {
            $data['date_metadata_update'] = CURRENT_DATE;
            $data['id'] = $id;
            $datas[] = $data;

            foreach (['keywords', 'tags'] as $key) {
                if (isset($data[$key])) {
                    if (!isset($tags_of[$id])) {
                        $tags_of[$id] = [];
                    }

                    foreach (explode(',', $data[$key]) as $tag_name) {
                        $tags_of[$id][] = $services['tags']->TagIdFromTagName($tag_name);
                    }
                }
            }
        } else {
            $errors[] = [
                'path' => $element_infos['path'],
                'type' => 'PWG-ERROR-NO-FS'
            ];
        }
    }

    if (!$simulate) {
        if (count($datas) > 0) {
            (new ImageRepository($conn))->massUpdates(
                [
                    'primary' => ['id'],
                    'update' => array_unique(
                        array_merge(
                            array_diff(
                                $site_reader->get_metadata_attributes(),
                              // keywords and tags fields are managed separately
                                ['keywords', 'tags']
                            ),
                            ['date_metadata_update']
                        )
                    )
                ],
                $datas,
                isset($_POST['meta_empty_overrides']) ? 0 : MASS_UPDATES_SKIP_EMPTY
            );
        }
        $services['tags']->setTagsOf($tags_of);
    }

    $template->append('footer_elements', '<!-- metadata update : ' . \Phyxo\Functions\Utils::get_elapsed_time($start, microtime(true)) . ' -->');

    $template->assign(
        'metadata_result',
        [
            'NB_ELEMENTS_DONE' => count($datas),
            'NB_ELEMENTS_CANDIDATES' => count($files),
            'NB_ERRORS' => count($errors),
        ]
    );
}

// +-----------------------------------------------------------------------+
// |                        template initialization                        |
// +-----------------------------------------------------------------------+

$result_title = '';
if (isset($simulate) and $simulate) {
    $result_title .= '[' . \Phyxo\Functions\Language::l10n('Simulation') . '] ';
}

// used_metadata string is displayed to inform admin which metadata will be
// used from files for synchronization
$used_metadata = implode(', ', $site_reader->get_metadata_attributes());
if ($site_is_remote and !isset($_POST['submit'])) {
    $used_metadata .= ' + ...';
}

$template->assign(
    [
        'SITE_URL' => $site_url,
        'U_SITE_MANAGER' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=site_manager',
        'L_RESULT_UPDATE' => $result_title . \Phyxo\Functions\Language::l10n('Search for new images in the directories'),
        'L_RESULT_METADATA' => $result_title . \Phyxo\Functions\Language::l10n('Metadata synchronization results'),
        'METADATA_LIST' => $used_metadata,
        //'U_HELP' => \Phyxo\Functions\URL::get_root_url().'admin/popuphelp.php?page=synchronize',
    ]
);

// +-----------------------------------------------------------------------+
// |                        introduction : choices                         |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit'])) {
    $tpl_introduction = [
        'sync' => $_POST['sync'],
        'sync_meta' => isset($_POST['sync_meta']) ? true : false,
        'display_info' => isset($_POST['display_info']) and $_POST['display_info'] == 1,
        'add_to_caddie' => isset($_POST['add_to_caddie']) and $_POST['add_to_caddie'] == 1,
        'subcats_included' => isset($_POST['subcats-included']) and $_POST['subcats-included'] == 1,
        'privacy_level_selected' => (int)@$_POST['privacy_level'],
        'meta_all' => isset($_POST['meta_all']) ? true : false,
        'meta_empty_overrides' => isset($_POST['meta_empty_overrides']) ? true : false,
    ];

    if (isset($_POST['cat']) and is_numeric($_POST['cat'])) {
        $cat_selected = [$_POST['cat']];
    } else {
        $cat_selected = [];
    }
} else {
    $tpl_introduction = [
        'sync' => 'dirs',
        'sync_meta' => true,
        'display_info' => false,
        'add_to_caddie' => false,
        'subcats_included' => true,
        'privacy_level_selected' => 0,
        'meta_all' => false,
        'meta_empty_overrides' => false,
    ];

    $cat_selected = [];

    if (isset($_GET['cat_id'])) {
        \Phyxo\Functions\Utils::check_input_parameter('cat_id', $_GET, false, PATTERN_ID);

        $cat_selected = [$_GET['cat_id']];
        $tpl_introduction['sync'] = 'files';
    }
}

$tpl_introduction['privacy_level_options'] = \Phyxo\Functions\Utils::get_privacy_level_options();

$template->assign('introduction', $tpl_introduction);

$result = (new CategoryRepository($conn))->findByField('site_id', $site_id);
$categories = $conn->result2array($result);
\Phyxo\Functions\Category::display_select_cat_wrapper($categories, $cat_selected, 'category_options', false);

if (count($errors) > 0) {
    foreach ($errors as $error) {
        $template->append(
            'sync_errors',
            [
                'ELEMENT' => $error['path'],
                'LABEL' => $error['type'] . ' (' . $error_labels[$error['type']][0] . ')'
            ]
        );
    }

    foreach ($error_labels as $error_type => $error_description) {
        $template->append(
            'sync_error_captions',
            [
                'TYPE' => $error_type,
                'LABEL' => $error_description[1]
            ]
        );
    }
}

if (count($infos) > 0 and isset($_POST['display_info']) and $_POST['display_info'] == 1) {
    foreach ($infos as $info) {
        $template->append(
            'sync_infos',
            [
                'ELEMENT' => $info['path'],
                'LABEL' => $info['info']
            ]
        );
    }
}

$template_filename = 'site_update';
