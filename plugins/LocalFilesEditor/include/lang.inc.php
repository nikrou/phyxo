<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

$languages = get_languages();

if (isset($_POST['edit']))
{
  $_POST['language'] = $_POST['language_select'];
}

if (isset($_POST['language']))
{
  $page['language'] = $_POST['language'];
}

if (!isset($page['language']) or !in_array($page['language'], array_keys($languages)))
{
  $page['language'] = get_default_language();
}

$template->assign('language', $page['language']);

$edited_file = PHPWG_ROOT_PATH.PWG_LOCAL_DIR.'language/'.$page['language'].'.lang.php';;

if (file_exists($edited_file))
{
  $content_file = file_get_contents($edited_file);
}
else
{
  $content_file = "<?php\n\n/* ".l10n('locfiledit_newfile')." */\n\n\n\n\n?>";
}

$selected = 0;
foreach (get_languages() as $language_code => $language_name)
{
  $file = PHPWG_ROOT_PATH.PWG_LOCAL_DIR.'language/'.$language_code.'.lang.php';

  $options[$language_code] = (file_exists($file) ? '&#x2714;' : '&#x2718;').' '.$language_name;

  if ($page['language'] == $language_code)
  {
    $selected = $language_code;
    $template->assign('show_default', array(
      array(
        'URL' => LOCALEDIT_PATH.'show_default.php?file=language/'.$language_code.'/common.lang.php',
        'FILE' => 'common.lang.php'
        ),
      array(
        'URL' => LOCALEDIT_PATH.'show_default.php?file=language/'.$language_code.'/admin.lang.php',
        'FILE' => 'admin.lang.php'
        )
      )
    );
  }
}

$template->assign(
  'css_lang_tpl',
  array(
    'SELECT_NAME' => 'language_select',
    'OPTIONS' => $options,
    'SELECTED' => $selected
    )
  );

$codemirror_mode = 'application/x-httpd-php';

