<?php
// +-----------------------------------------------------------------------+
// | User Tags  - a plugin for Phyxo                                       |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2010-2014 Nicolas Roudaire        http://www.nikrou.net  |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

class t4u_Ws
{
  public function addMethods($arr) {
    $service = &$arr[0];

    $service->addMethod(T4U_WS.'list', array($this, 'tagsList'), 
                        array('q' => array()),
                        'retrieves a list of tags than can be filtered'
                        );
    
    $service->addMethod(T4U_WS.'update', array($this, 'updateTags'), 
                        array('image_id' => array(),
                              'tags' => array('default' => array())
                              ),
                        'Updates (add or remove) tags associated to an image (POST method only)'
                        );
  }
  
  public function tagsList($params, &$service) {
    $query = 'SELECT id AS tag_id, name AS tag_name FROM '.TAGS_TABLE;
    if (!empty($params['q'])) {
      $query .= sprintf(' WHERE LOWER(name) like \'%%%s%%\'', strtolower(pwg_db_real_escape_string($params['q'])));
    }
    
    $tagslist = $this->__makeTagsList($query);
    unset($tagslist['__associative_tags']);
    $cmp = create_function('$a,$b', 'return strcasecmp($a[\'name\'], $b[\'name\']);');
    usort($tagslist, $cmp);
    
    return $tagslist;
  }
  
  public function updateTags($params, &$service) {
    if (!$service->isPost()) {
      return new PwgError(405, "This method requires HTTP POST");
    }

    if (!t4u_Config::getInstance()->hasPermission('add') && !t4u_Config::getInstance()->hasPermission('delete')) { 
      return array('error' => l10n('You are not allowed to add nor delete tags'));
    }
    
    if (empty($params['tags'])) {
      $params['tags'] = array();
    }
    $message = '';

    $query = 'SELECT tag_id, name AS tag_name';
    $query .= ' FROM '.IMAGE_TAG_TABLE.' AS it';
    $query .= ' JOIN '.TAGS_TABLE.' AS t ON t.id = it.tag_id';
    $query .= sprintf(' WHERE image_id = %s', pwg_db_real_escape_string($params['image_id']));
    
    $current_tags = $this->__makeTagsList($query);
    $current_tags_ids = array_keys($current_tags['__associative_tags']);
    if (empty($params['tags'])) {
      $tags_to_associate = array();
    } else {
      $tags_to_associate = explode(',', $params['tags']);
    }

    $removed_tags = array_diff($current_tags_ids, $tags_to_associate);
    $new_tags = array_diff($tags_to_associate, $current_tags_ids);

    if (count($removed_tags)>0) {
      if (!t4u_Config::getInstance()->hasPermission('delete')) { 
        $message['error'][] = l10n('You are not allowed to delete tags');
      } else {
        $message['info'] = l10n('Tags updated');
      }
    }
    if (count($new_tags)>0) {
      if (!t4u_Config::getInstance()->hasPermission('add')) { 
        $message['error'][] = l10n('You are not allowed to add tags');
        $tags_to_associate = array_diff($tags_to_associate, $new_tags);
      } else {
        $message['info'] = l10n('Tags updated');
      }
    } 

    if (empty($message['error'])) {
      if (empty($tags_to_associate)) { // remove all tags for an image
        $query = 'DELETE FROM '.IMAGE_TAG_TABLE;
        $query .= sprintf(' WHERE image_id = %d', pwg_db_real_escape_string($params['image_id']));
        pwg_query($query);
      } else {
        $tag_ids = get_tag_ids(implode(',', $tags_to_associate));
        set_tags($tag_ids, $params['image_id']);  
      }
    }
    
    return $message;
  }

  private function __makeTagsList($query) {
    $result = pwg_query($query);
    
    $tagslist = array();
    $associative_tags = array();
    while ($row = pwg_db_fetch_assoc($result)) {
      $associative_tags['~~'.$row['tag_id'].'~~'] = $row['tag_name'];
      $tagslist[] = array('id' => '~~'.$row['tag_id'].'~~',
                          'name' => $row['tag_name']
                          );
      
    }
    $tagslist['__associative_tags'] = $associative_tags;
    
    return $tagslist;
  }
}
