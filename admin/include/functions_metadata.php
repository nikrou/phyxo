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

/**
 * @package functions\admin\metadata
 */


include_once(PHPWG_ROOT_PATH . '/include/functions_metadata.inc.php');

/**
 * Returns IPTC metadata to sync from a file, depending on IPTC mapping.
 * @toto : clean code (factorize foreach)
 *
 * @param string $file
 * @return array
 */
function get_sync_iptc_data($file)
{
    global $conf;

    $map = $conf['use_iptc_mapping'];

    $iptc = get_iptc_data($file, $map);

    foreach ($iptc as $pwg_key => $value) {
        if (in_array($pwg_key, array('date_creation', 'date_available'))) {
            if (preg_match('/(\d{4})(\d{2})(\d{2})/', $value, $matches)) {
                $year = $matches[1];
                $month = $matches[2];
                $day = $matches[3];

                if (!checkdate($month, $day, $year)) {
                    // we suppose the year is correct
                    $month = 1;
                    $day = 1;
                }

                $iptc[$pwg_key] = $year . '-' . $month . '-' . $day;
            }
        }
    }

    if (isset($iptc['keywords'])) {
        // official keywords separator is the comma
        $iptc['keywords'] = preg_replace('/[.;]/', ',', $iptc['keywords']);
        $iptc['keywords'] = preg_replace('/,+/', ',', $iptc['keywords']);
        $iptc['keywords'] = preg_replace('/^,+|,+$/', '', $iptc['keywords']);

        $iptc['keywords'] = implode(
            ',',
            array_unique(
                explode(
                    ',',
                    $iptc['keywords']
                )
            )
        );
    }

    foreach ($iptc as $pwg_key => $value) {
        $iptc[$pwg_key] = addslashes($iptc[$pwg_key]);
    }

    return $iptc;
}

/**
 * Returns EXIF metadata to sync from a file, depending on EXIF mapping.
 *
 * @param string $file
 * @return array
 */
function get_sync_exif_data($file)
{
    global $conf;

    $exif = get_exif_data($file, $conf['use_exif_mapping']);

    foreach ($exif as $pwg_key => $value) {
        if (in_array($pwg_key, array('date_creation', 'date_available'))) {
            if (preg_match('/^(\d{4}).(\d{2}).(\d{2}) (\d{2}).(\d{2}).(\d{2})/', $value, $matches)) {
                if ($matches[1] != '0000' && $matches[2] != '00' && $matches[3] != '00'
                    && $matches[4] != '00' && $matches[5] != '00' && $matches[6] != '00') {
                    $exif[$pwg_key] = $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . $matches[4] . ':' . $matches[5] . ':' . $matches[6];
                } else {
                    unset($exif[$pwg_key]);
                }
            } elseif (preg_match('/^(\d{4}).(\d{2}).(\d{2})/', $value, $matches)) {
                $exif[$pwg_key] = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
            } else {
                unset($exif[$pwg_key]);
                continue;
            }
        }
        if (!empty($exif[$pwg_key])) {
            $exif[$pwg_key] = addslashes($exif[$pwg_key]); // @TODO: why addslashes ???
        }
    }

    return $exif;
}

/**
 * Get all potential file metadata fields, including IPTC and EXIF.
 *
 * @return string[]
 */
function get_sync_metadata_attributes()
{
    global $conf;

    $update_fields = array('filesize', 'width', 'height');

    if ($conf['use_exif']) {
        $update_fields =
            array_merge(
            $update_fields,
            array_keys($conf['use_exif_mapping']),
            array('latitude', 'longitude')
        );
    }

    if ($conf['use_iptc']) {
        $update_fields =
            array_merge(
            $update_fields,
            array_keys($conf['use_iptc_mapping'])
        );
    }

    return array_unique($update_fields);
}

/**
 * Get all metadata of a file.
 *
 * @param array $infos - (path[, representative_ext])
 * @return array - includes data provided in $infos
 */
function get_sync_metadata($infos)
{
    global $conf;

    $file = PHPWG_ROOT_PATH . $infos['path'];
    $fs = @filesize($file);

    if ($fs === false) {
        return false;
    }

    $infos['filesize'] = floor($fs / 1024);

    if (isset($infos['representative_ext'])) {
        $file = \Phyxo\Functions\Utils::original_to_representative($file, $infos['representative_ext']);
    }

    if ($image_size = @getimagesize($file)) {
        $infos['width'] = $image_size[0];
        $infos['height'] = $image_size[1];
    }

    if ($conf['use_exif']) {
        $exif = get_sync_exif_data($file);
        $infos = array_merge($infos, $exif);
    }

    if ($conf['use_iptc']) {
        $iptc = get_sync_iptc_data($file);
        $infos = array_merge($infos, $iptc);
    }

    return $infos;
}

/**
 * Sync all metadata of a list of images.
 * Metadata are fetched from original files and saved in database.
 *
 * @param int[] $ids
 */
function sync_metadata($ids)
{
    global $conf, $conn, $services;

    if (!defined('CURRENT_DATE')) {
        define('CURRENT_DATE', date('Y-m-d'));
    }

    $datas = array();
    $tags_of = array();

    $query = 'SELECT id, path, representative_ext FROM ' . IMAGES_TABLE;
    $query .= ' WHERE id ' . $conn->in($ids);

    $result = $conn->db_query($query);
    while ($data = $conn->db_fetch_assoc($result)) {
        $data = get_sync_metadata($data);
        if ($data === false) {
            continue;
        }

        $id = $data['id'];
        foreach (array('keywords', 'tags') as $key) {
            if (isset($data[$key])) {
                if (!isset($tags_of[$id])) {
                    $tags_of[$id] = array();
                }

                foreach (explode(',', $data[$key]) as $tag_name) {
                    $tags_of[$id][] = $services['tags']->tagIdFromTagName($tag_name);
                }
            }
        }

        $data['date_metadata_update'] = CURRENT_DATE;

        $datas[] = $data;
    }

    if (count($datas) > 0) {
        $update_fields = get_sync_metadata_attributes();
        $update_fields[] = 'date_metadata_update';

        $update_fields = array_diff(
            $update_fields,
            array('tags', 'keywords')
        );

        $conn->mass_updates(
            IMAGES_TABLE,
            array(
                'primary' => array('id'),
                'update' => $update_fields
            ),
            $datas,
            MASS_UPDATES_SKIP_EMPTY
        );
    }

    $services['tags']->setTagsOf($tags_of);
}

/**
 * Returns an array associating element id (images.id) with its complete
 * path in the filesystem
 *
 * @param int $category_id
 * @param int $site_id
 * @param boolean $recursive
 * @param boolean $only_new
 * @return array
 */
function get_filelist($category_id = '', $site_id = 1, $recursive = false, $only_new = false)
{
    global $conn;

    // filling $cat_ids : all categories required
    $cat_ids = array();

    $query = 'SELECT id FROM ' . CATEGORIES_TABLE;
    $query .= ' WHERE site_id = ' . $site_id . ' AND dir IS NOT NULL';
    if (is_numeric($category_id)) {
        if ($recursive) {
            $query .= ' AND uppercats ' . $conn::REGEX_OPERATOR . ' \'(^|,)' . $category_id . '(,|$)\'';
        } else {
            $query .= ' AND id = ' . $category_id;
        }
    }
    $result = $conn->db_query($query);
    while ($row = $conn->db_fetch_assoc($result)) {
        $cat_ids[] = $row['id'];
    }

    if (count($cat_ids) == 0) {
        return array();
    }

    $query = 'SELECT id, path, representative_ext FROM ' . IMAGES_TABLE;
    $query .= ' WHERE storage_category_id ' . $conn->in($cat_ids);
    if ($only_new) {
        $query .= ' AND date_metadata_update IS NULL';
    }

    return $conn->query2array($query, 'id');
}
