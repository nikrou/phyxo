<?php
// +-----------------------------------------------------------------------+
// | PhpWebGallery - a PHP based picture gallery                           |
// | Copyright (C) 2002-2003 Pierrick LE GALL - pierrick@phpwebgallery.net |
// | Copyright (C) 2003-2005 PhpWebGallery Team - http://phpwebgallery.net |
// +-----------------------------------------------------------------------+
// | branch        : BSF (Best So Far)
// | file          : $RCSfile$
// | last update   : $Date$
// | last modifier : $Author$
// | revision      : $Revision$
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

if( !defined("PHPWG_ROOT_PATH") )
{
	die ("Hacking attempt!");
}

include_once( PHPWG_ROOT_PATH.'admin/include/isadmin.inc.php' );
//-------------------------------------------------------- sections definitions
if (!isset($_GET['section']))
{
  $page['section'] = 'general';
}
else
{
  $page['section'] = $_GET['section'];
}
//------------------------------------------------------ $conf reinitialization
$result = pwg_query('SELECT param,value FROM '.CONFIG_TABLE);
while ($row = mysql_fetch_array($result))
{
  $conf[$row['param']] = $row['value'];
  // if the parameter is present in $_POST array (if a form is submited), we
  // override it with the submited value
  if (isset($_POST[$row['param']]))
  {
    $conf[$row['param']] = $_POST[$row['param']];
  }
}					   
//------------------------------ verification and registration of modifications
if (isset($_POST['submit']))
{
  $int_pattern = '/^\d+$/';
  switch ($page['section'])
  {
    case 'general' :
    {
      break;
    }
    case 'comments' :
    {
      // the number of comments per page must be an integer between 5 and 50
      // included
      if (!preg_match($int_pattern, $_POST['nb_comment_page'])
           or $_POST['nb_comment_page'] < 5
           or $_POST['nb_comment_page'] > 50)
      {
        array_push($page['errors'], $lang['conf_nb_comment_page_error']);
      }
      break;
    }
    case 'default' :
    {
      // periods must be integer values, they represents number of days
      if (!preg_match($int_pattern, $_POST['recent_period'])
          or $_POST['recent_period'] <= 0)
      {
        array_push($page['errors'], $lang['periods_error']);
      }
      break;
    }
  }
  
  // updating configuration if no error found
  if (count($page['errors']) == 0)
  {
    echo '<pre>'; print_r($_POST); echo '</pre>';
    $result = pwg_query('SELECT * FROM '.CONFIG_TABLE);
    while ($row = mysql_fetch_array($result))
    {
      if (isset($_POST[$row['param']]))
      {
        $query = '
UPDATE '.CONFIG_TABLE.'
  SET value = \''. str_replace("\'", "''", $_POST[$row['param']]).'\'
  WHERE param = \''.$row['param'].'\'
;';
        pwg_query($query);
      }
    }
    array_push($page['infos'], $lang['conf_confirmation']);
  }
}

//----------------------------------------------------- template initialization
$template->set_filenames( array('config'=>'admin/configuration.tpl') );

$action = PHPWG_ROOT_PATH.'admin.php?page=configuration';
$action.= '&amp;section='.$page['section'];

$template->assign_vars(
  array(
    'L_YES'=>$lang['yes'],
    'L_NO'=>$lang['no'],
    'L_SUBMIT'=>$lang['submit'],
    'L_RESET'=>$lang['reset'],

    'U_HELP' => PHPWG_ROOT_PATH.'/popuphelp.php?page=configuration',
    
    'F_ACTION'=>add_session_id($action)
    ));

switch ($page['section'])
{
  case 'general' :
  {
    $history_yes = ($conf['log']=='true')?'checked="checked"':'';
    $history_no  = ($conf['log']=='false')?'checked="checked"':'';
    $lock_yes = ($conf['gallery_locked']=='true')?'checked="checked"':'';
    $lock_no = ($conf['gallery_locked']=='false')?'checked="checked"':'';
    
    $template->assign_block_vars(
      'general',
      array(
        'HISTORY_YES'=>$history_yes,
        'HISTORY_NO'=>$history_no,
        'GALLERY_LOCKED_YES'=>$lock_yes,
        'GALLERY_LOCKED_NO'=>$lock_no,
        ));
    break;
  }
  case 'comments' :
  {
    $all_yes = ($conf['comments_forall']=='true')?'checked="checked"':'';
    $all_no  = ($conf['comments_forall']=='false')?'checked="checked"':'';
    $validate_yes = ($conf['comments_validation']=='true')?'checked="checked"':'';
    $validate_no = ($conf['comments_validation']=='false')?'checked="checked"':'';
      
    $template->assign_block_vars(
      'comments',
      array(
        'NB_COMMENTS_PAGE'=>$conf['nb_comment_page'],
        'COMMENTS_ALL_YES'=>$all_yes,
        'COMMENTS_ALL_NO'=>$all_no,
        'VALIDATE_YES'=>$validate_yes,
        'VALIDATE_NO'=>$validate_no
        ));
    break;
  }
  case 'default' :
  {
    $show_yes = ($conf['show_nb_comments']=='true')?'checked="checked"':'';
    $show_no = ($conf['show_nb_comments']=='false')?'checked="checked"':'';
    $expand_yes = ($conf['auto_expand']=='true')?'checked="checked"':'';
    $expand_no  = ($conf['auto_expand']=='false')?'checked="checked"':'';
      
    $template->assign_block_vars(
      'default',
      array(
        'NB_IMAGE_LINE'=>$conf['nb_image_line'],
        'NB_ROW_PAGE'=>$conf['nb_line_page'],
        'CONF_RECENT'=>$conf['recent_period'],
        'NB_COMMENTS_PAGE'=>$conf['nb_comment_page'],
        'EXPAND_YES'=>$expand_yes,
        'EXPAND_NO'=>$expand_no,
        'SHOW_COMMENTS_YES'=>$show_yes,
        'SHOW_COMMENTS_NO'=>$show_no
        ));
    
    $blockname = 'default.language_option';
    
    foreach (get_languages() as $language_code => $language_name)
    {
      if (isset($_POST['submit']))
      {
        $selected =
          $_POST['default_language'] == $language_code
            ? 'selected="selected"' : '';
      }
      else if ($conf['default_language'] == $language_code)
      {
        $selected = 'selected="selected"';
      }
      else
      {
        $selected = '';
      }
      
      $template->assign_block_vars(
        $blockname,
        array(
          'VALUE'=> $language_code,
          'CONTENT' => $language_name,
          'SELECTED' => $selected
          ));
    }

    $blockname = 'default.template_option';

    foreach (get_templates() as $pwg_template)
    {
      if (isset($_POST['submit']))
      {
        $selected =
          $_POST['default_template'] == $pwg_template
            ? 'selected="selected"' : '';
      }
      else if ($conf['default_template'] == $pwg_template)
      {
        $selected = 'selected="selected"';
      }
      else
      {
        $selected = '';
      }
      
      $template->assign_block_vars(
        $blockname,
        array(
          'VALUE'=> $pwg_template,
          'CONTENT' => $pwg_template,
          'SELECTED' => $selected
          )
        );
    }

 
    break;
  }
}
//----------------------------------------------------------- sending html code
$template->assign_var_from_handle('ADMIN_CONTENT', 'config');
?>
