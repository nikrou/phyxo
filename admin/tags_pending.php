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

if (!defined('TAGS_BASE_URL')) {
    die ('Hacking attempt!');
}

if (!empty($_POST['tag_ids'])) {
    if (!empty($_POST['validate'])) {
        $services['tags']->validateTags($_POST['tag_ids']);
    } elseif ($_POST['reject']) {
        $services['tags']->rejectTags($_POST['tag_ids']);
    }
}

$template->assign('tags', $services['tags']->getPendingTags());
