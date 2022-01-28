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
 *                           configuration page
 *
 * Set configuration parameters that are not in the table config. In the
 * application, configuration parameters are considered in the same way
 * coming from config table or config_default.inc.php.
 *
 * It is recommended to let config_default.inc.php as provided and to
 * overwrite configuration in your local configuration file
 * local/config/config.inc.php. See tools/config.inc.php as an example.
 *
 * Why having some parameters in config table and others in
 * config_*.inc.php? Modifying config_*.inc.php is a "hard" task for low
 * skilled users, they need a GUI for this : admin/configuration. But only
 * parameters that might be modified by low skilled users are in config
 * table, other parameters are in config_*.inc.php
 */

// +-----------------------------------------------------------------------+
// |                                 misc                                  |
// +-----------------------------------------------------------------------+

// order_by_custom and order_by_inside_category_custom : for non common pattern
// you can define special ORDER configuration
//
// $conf['order_by_custom'] = ' ORDER BY date_available DESC, file ASC, id ASC';

// order_by_inside_category : inside a category, images can also be ordered
// by rank. A manually defined rank on each image for the category.
//
// $conf['order_by_inside_category_custom'] = $conf['order_by_custom'];

// picture_ext : file extensions for picture file, must be a subset of
// file_ext
$conf['picture_ext'] = ['jpg', 'JPG', 'jpeg', 'JPEG', 'png', 'PNG', 'gif', 'GIF'];

// file_ext : file extensions (case sensitive) authorized
$conf['file_ext'] = array_merge(
  $conf['picture_ext'],
  ['tiff', 'tif', 'mpg', 'zip', 'avi', 'mp3', 'ogg', 'pdf']
);

// top_number : number of element to display for "best rated" and "most
// visited" categories
$conf['top_number'] = 15;

// anti-flood_time : number of seconds between 2 comments : 0 to disable
$conf['anti-flood_time'] = 60;

// qualified spam comments are not registered (false will register them
// but they will require admin validation)
$conf['comment_spam_reject'] = true;

// maximum number of links in a comment before it is qualified spam
$conf['comment_spam_max_links'] = 3;

// calendar_datefield : date field of table "images" used for calendar
// catgory
$conf['calendar_datefield'] = 'date_creation';

// calendar_show_any : the calendar shows an aditional 'any' button in the
// year/month/week/day navigation bars
$conf['calendar_show_any'] = true;

// calendar_show_empty : the calendar shows month/weeks/days even if there are
//no elements for these
$conf['calendar_show_empty'] = true;

// newcat_default_commentable : at creation, must a category be commentable
// or not ?
$conf['newcat_default_commentable'] = true;

// newcat_default_visible : at creation, must a category be visible or not ?
// Warning : if the parent category is invisible, the category is
// automatically create invisible. (invisible = locked)
$conf['newcat_default_visible'] = true;

// newcat_default_status : at creation, must a category be public or private
// ? Warning : if the parent category is private, the category is
// automatically create private.
$conf['newcat_default_status'] = 'public';

// level_separator : character string used for separating a category level
// to the sub level. Suggestions : ' / ', ' &raquo; ', ' &rarr; ', ' - ',
// ' &gt;'
$conf['level_separator'] = ' / ';

// paginate_pages_around : on paginate navigation bar, how many pages
// display before and after the current page ?
$conf['paginate_pages_around'] = 2;

// show_version : shall the version of Phyxo be displayed at the
// bottom of each page ?
$conf['show_version'] = true;

// meta_ref to reference multiple sets of incorporated pages or elements
// Set it false to avoid referencing in google, and other search engines.
$conf['meta_ref'] = true;

// links : list of external links to add in the menu. An example is the best
// than a long explanation :
// If the array is empty, the "Links" box won't be displayed on the main page.
$conf['links'] = [];

// random_index_redirect: list of 'internal' links to use when no section is defined on index.php.
// An example is the best than a long explanation :
//
//  for each link is associated a php condition
//  '' condition is equivalent to 'return true;'
//  $conf['random_index_redirect'] = array(
//    '/best_rated' => 'return true;',
//    '/recent_pics' => 'return Users::isGuest();',
//    '/random' => '',
//    '/categories' => '',
//    );
$conf['random_index_redirect'] = [];

// List of notes to display on all header page
// example $conf['header_notes']  = array('Test', 'Hello');
$conf['header_notes'] = [];

// show_thumbnail_caption : on thumbnails page, show thumbnail captions ?
$conf['show_thumbnail_caption'] = true;

// allow_random_representative : do you wish Phyxo to search among
// categories elements a new representative at each reload ?
//
// If false, an element is randomly or manually chosen to represent its
// category and remains the representative as long as an admin does not
// change it.
//
// Warning : setting this parameter to true is CPU consuming. Each time you
// change the value of this parameter from false to true, an administrator
// must update categories informations in screen [Admin > General >
// Maintenance].
$conf['allow_random_representative'] = false;

// representative_cache_on_level: if a thumbnail is chosen as representative
// but has higher privacy level than current user, Phyxo randomly selects
// another thumbnail. Should be store this thumbnail in cache to avoid
// another consuming SQL query on next page refresh?
$conf['representative_cache_on_level'] = true;

// representative_cache_on_subcats: if a category (= album) only contains
// sub-categories, Phyxo randomly selects a thumbnail among sub-categories
// representative. Should we store this thumbnail in cache to avoid another
// "slightly" consuming SQL query on next page refresh?
$conf['representative_cache_on_subcats'] = true;

// allow_html_descriptions : authorize administrators to use HTML in
// category and element description.
$conf['allow_html_descriptions'] = true;

// image level permissions available in the admin interface
$conf['available_permission_levels'] = [0, 1, 2, 4, 8];

// check_upgrade_feed: check if there are database upgrade required. Set to
// true, a message will strongly encourage you to upgrade your database if
// needed.
//
// This configuration parameter is set to true in BSF branch and to false
// elsewhere.
$conf['check_upgrade_feed'] = true;

// rate_items: available rates for a picture
$conf['rate_items'] = [0, 1, 2, 3, 4, 5];

// Define using double password type in admin's users management panel
$conf['double_password_type_in_admin'] = false;

// how should we check for unicity when adding a photo. Can be 'md5sum' or
// 'filename'
$conf['uniqueness_mode'] = 'md5sum';

// Library used for image resizing. Value could be 'auto', 'imagick',
// 'ext_imagick' or 'gd'. If value is 'auto', library will be choosen in this
// order. If choosen library is not available, another one will be picked up.
$conf['graphics_library'] = 'auto';

// If library used is external installation of ImageMagick ('ext_imagick'),
// you can define imagemagick directory.
$conf['ext_imagick_dir'] = '';

// how many user comments to display by default on comments.php. Use 'all'
// to display all user comments without pagination. Default available values
// are array(5,10,20,50,'all') but you can set any other numeric value.
$conf['comments_page_nb_comments'] = 10;

// +-----------------------------------------------------------------------+
// |                               metadata                                |
// +-----------------------------------------------------------------------+

// show_iptc: Show IPTC metadata on picture.php if asked by user
$conf['show_iptc'] = false;

// show_iptc_mapping : is used for showing IPTC metadata on picture.php
// page. For each key of the array, you need to have the same key in the
// $lang array. For example, if my first key is 'iptc_keywords' (associated
// to '2#025') then you need to have $lang['iptc_keywords'] set in
// language/$user['language']/common.lang.php. If you don't have the lang
// var set, the key will be simply displayed
//
// To know how to associated iptc_field with their meaning, use
// tools/metadata.php
$conf['show_iptc_mapping'] = [
    'iptc_keywords' => '2#025',
    'iptc_caption_writer' => '2#122',
    'iptc_byline_title' => '2#085',
    'iptc_caption' => '2#120'
];

// use_iptc: Use IPTC data during database synchronization with files
// metadata
$conf['use_iptc'] = false;

// use_iptc_mapping : in which IPTC fields will Phyxo find image
// information ? This setting is used during metadata synchronisation. It
// associates a phyxo_images column name to a IPTC key
$conf['use_iptc_mapping'] = [
    'keywords' => '2#025',
    'date_creation' => '2#055',
    'author' => '2#122',
    'name' => '2#005',
    'comment' => '2#120'
];

// show_exif: Show EXIF metadata on picture.php (table or line presentation
// avalaible)
$conf['show_exif'] = true;

// show_exif_fields : in EXIF fields, you can choose to display fields in
// sub-arrays, for example ['COMPUTED']['ApertureFNumber']. for this, add
// 'COMPUTED;ApertureFNumber' in $conf['show_exif_fields']
//
// The key displayed in picture.php will be $lang['exif_field_Make'] for
// example and if it exists. For compound fields, only take into account the
// last part : for key 'COMPUTED;ApertureFNumber', you need
// $lang['exif_field_ApertureFNumber']
//
// for PHP version newer than 4.1.2 :
// $conf['show_exif_fields'] = array('CameraMake','CameraModel','DateTime');
//
$conf['show_exif_fields'] = [
    'Make',
    'Model',
    'DateTimeOriginal',
    'COMPUTED;ApertureFNumber'
];

// use_exif: Use EXIF data during database synchronization with files
// metadata
$conf['use_exif'] = true;

// use_exif_mapping: same behaviour as use_iptc_mapping
$conf['use_exif_mapping'] = [
    'date_creation' => 'DateTimeOriginal'
];

// allow_html_in_metadata: in case the origin of the photo is unsecure (user
// upload), we remove HTML tags to avoid XSS (malicious execution of
// javascript)
$conf['allow_html_in_metadata'] = false;

// +-----------------------------------------------------------------------+
// |                            debug/performance                          |
// +-----------------------------------------------------------------------+

// does the guest have access ?
// (not a security feature, set your categories "private" too)
// If false it'll be redirected from index.php to identification.php
$conf['guest_access'] = true;

// +-----------------------------------------------------------------------+
// |                               history                                 |
// +-----------------------------------------------------------------------+

// nb_logs_page :  how many logs to display on a page
$conf['nb_logs_page'] = 300;

// +-----------------------------------------------------------------------+
// |                                 urls                                  |
// +-----------------------------------------------------------------------+

// gallery_url : you can set a specific URL for the home page of your
// gallery. This is for very specific use and you don't need to change this
// setting when move your gallery to a new directory or a new domain name.
$conf['gallery_url'] = null;

// tag_url_style : one of 'id-tag' (default), 'id' or 'tag'.
// Note that if you choose 'tag' and the url (ascii) representation of your
// tags is not unique, all tags with the same url representation will be shown
$conf['tag_url_style'] = 'id-tag';

// +-----------------------------------------------------------------------+
// |                                 tags                                  |
// +-----------------------------------------------------------------------+

// full_tag_cloud_items_number: number of tags to show in the full tag
// cloud. Only the most represented tags will be shown
$conf['full_tag_cloud_items_number'] = 200;

// menubar_tag_cloud_items_number: number of tags to show in the tag
// cloud in the menubar. Only the most represented tags will be shown
$conf['menubar_tag_cloud_items_number'] = 20;

// content_tag_cloud_items_number: number of related tags to show in the tag
// cloud on the content page, when the current section is not a set of
// tags. Only the most represented tags will be shown
$conf['content_tag_cloud_items_number'] = 12;

// tags_levels: number of levels to use for display. Each level is bind to a
// CSS class tagLevelX.
$conf['tags_levels'] = 5;

// tags_default_display_mode: group tags by letter or display a tag cloud by
// default? 'letters' or 'cloud'.
$conf['tags_default_display_mode'] = 'cloud';

// tag_letters_column_number: how many columns to display tags by letter
$conf['tag_letters_column_number'] = 4;

// +-----------------------------------------------------------------------+
// | Notification by mail                                                  |
// +-----------------------------------------------------------------------+

// Default Value for nbm user
$conf['nbm_default_value_user_enabled'] = false;

// Search list user to send quickly (List all without to check news)
// More quickly but less fun to use
$conf['nbm_list_all_enabled_users_to_send'] = false;

// Max time used on one pass in order to send mails.
// Timeout delay ratio.
$conf['nbm_max_treatment_timeout_percent'] = 0.8;

// If timeout cannot be compite with nbm_max_treatment_timeout_percent,
// nbm_treatment_timeout_default is used by default
$conf['nbm_treatment_timeout_default'] = 20;

// Parameters used in get_recent_post_dates for the 2 kind of notification
$conf['recent_post_dates'] = [
    'RSS' => ['max_dates' => 5, 'max_elements' => 6, 'max_cats' => 6],
    'NBM' => ['max_dates' => 7, 'max_elements' => 3, 'max_cats' => 9]
];

// the author shown in the RSS feed <author> element
$conf['rss_feed_author'] = 'Phyxo notifier';

// +-----------------------------------------------------------------------+
// | Set admin layout                                                      |
// +-----------------------------------------------------------------------+

// Maximum number of images to be returned foreach call to the web service
$conf['ws_max_images_per_page'] = 500;

// Maximum number of users to be returned foreach call to the web service
$conf['ws_max_users_per_page'] = 1000;

// +-----------------------------------------------------------------------+
// | Filter                                                                |
// +-----------------------------------------------------------------------+
// $conf['filter_pages'] contains configuration for each pages
//   o If values are not defined for a specific page, default value are used
//   o Array is composed by the basename of each page without extention
//   o List of value names:
//     - used: filter function are used
//       (if false nothing is done [start, cancel, stop, ...]
//     - cancel: cancel current started filter
//     - add_notes: add notes about current started filter on the header
//   o Empty configuration in order to disable completely filter functions
//     No filter, No icon,...
//     $conf['filter_pages'] = array();
$conf['filter_pages'] = [
    // Default page
    'default' => [
        'used' => true, 'cancel' => false, 'add_notes' => false
    ],
    // Real pages
    'index' => ['add_notes' => true],
    'tags' => ['add_notes' => true],
    'search' => ['add_notes' => true],
    'comments' => ['add_notes' => true],
    'admin' => ['used' => false],
    'feed' => ['used' => false],
    'notification' => ['used' => false],
    'nbm' => ['used' => false],
    'profile' => ['used' => false],
    'ws' => ['used' => false],
    'identification' => ['cancel' => true],
    'install' => ['cancel' => true],
    'password' => ['cancel' => true],
    'register' => ['cancel' => true],
];

// enable the synchronization method for adding photos
$conf['enable_synchronization'] = false;

// permitted characters for files/directoris during synchronization
$conf['sync_chars_regex'] = '/^[a-zA-Z0-9-_.]+$/';

// folders name exluded during synchronization
$conf['sync_exclude_folders'] = [];

// PEM url (default is https://ext.phyxo.net)
$conf['alternative_pem_url'] = '';

// categories ID on PEM
$conf['pem_languages_category'] = 1;
$conf['pem_plugins_category'] = 2;
$conf['pem_themes_category'] = 3;

// based on the EXIF "orientation" tag, should we rotate photos added in the
// upload form or through pwg.images.addSimple web API method?
$conf['upload_form_automatic_rotation'] = true;

// 0-'auto', 1-'derivative' 2-'script'
$conf['derivative_url_style'] = 0;

$conf['chmod_value'] = substr_compare(PHP_SAPI, 'apa', 0, 3) == 0 ? 0777 : 0755;

// 'small', 'medium' or 'large'
$conf['derivative_default_size'] = 'medium';

// below which size (in pixels, ie width*height) do we remove metadata
// EXIF/IPTC... from derivative?
$conf['derivatives_strip_metadata_threshold'] = 256000;

//Maximum Ajax requests at once, for thumbnails on-the-fly generation
$conf['max_requests'] = 3;

// one of '', 'images', 'all'
//TODO: Put this in admin and also manage .htaccess in #sites and upload folders
$conf['original_url_protection'] = '';


// Default behaviour when a new album is created: should the new album inherit the group/user
// permissions from its parent? Note that config is only used for Ftp synchro,
// and if that option is not explicitly transmit when the album is created.
$conf['inheritance_by_default'] = false;

// 'png' or 'jpg': your uploaded TIF photos will have a representative in
// JPEG or PNG file format
$conf['tiff_representative_ext'] = 'png';

// in the upload form, let users upload only picture_exts or all file_exts?
// for some file types, Phyxo will try to generate a pwg_representative
// (TIFF, videos, PDF)
$conf['upload_form_all_types'] = false;

// If we try to generate a pwg_representative for a video we use ffmpeg. If
// "ffmpeg" is not visible by the web user, you can define the full path of
// the directory where "ffmpeg" executable is.
$conf['ffmpeg_dir'] = '';
