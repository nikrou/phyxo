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

if (!defined("LANGUAGES_BASE_URL")) {
    die ("Hacking attempt!");
}

use Phyxo\Language\Languages;

$languages = new Languages($conn);

//--------------------------------------------------perform requested actions
if (isset($_GET['action']) and isset($_GET['language'])) {
    $page['errors'] = $languages->performAction($_GET['action'], $_GET['language']);

    if (empty($page['errors'])) {
        redirect(LANGUAGES_BASE_URL.'&section=installed');
    }
}

// +-----------------------------------------------------------------------+
// |                     start template output                             |
// +-----------------------------------------------------------------------+
$default_language = $services['users']->getDefaultLanguage();

$tpl_languages = array();

foreach($languages->getFsLanguages() as $language_id => $language) {
    $language['u_action'] = add_url_params(LANGUAGES_BASE_URL.'&amp;section=installed', array('language' => $language_id));

    if (in_array($language_id, array_keys($languages->getDbLanguages()))) {
        $language['state'] = 'active';
        $language['deactivable'] = true;

        if (count($languages->getDbLanguages()) <= 1) {
            $language['deactivable'] = false;
            $language['deactivate_tooltip'] = l10n('Impossible to deactivate this language, you need at least one language.');
        }

        if ($language_id == $default_language) {
            $language['deactivable'] = false;
            $language['deactivate_tooltip'] = l10n('Impossible to deactivate this language, first set another language as default.');
        }
    } else {
        $language['state'] = 'inactive';
    }

    if ($language_id == $default_language) {
        $language['is_default'] = true;
        array_unshift($tpl_languages, $language);
    } else {
        $language['is_default'] = false;
        $tpl_languages[] = $language;
    }
}

$template->assign(array('languages' => $tpl_languages));
$template->append('language_states', 'active');
$template->append('language_states', 'inactive');

$missing_language_ids = array_diff(
    array_keys($languages->getDbLanguages()),
    array_keys($languages->getFsLanguages())
);

foreach($missing_language_ids as $language_id) {
    $query = 'UPDATE '.USER_INFOS_TABLE.' SET language = \''.$services['users']->getDefaultLanguage().'\'';
    $query .= ' WHERE language = \''.$language_id.'\';';
    $conn->db_query($query);

    $query = 'DELETE FROM '.LANGUAGES_TABLE.' WHERE id= \''.$language_id.'\';';
    $conn->db_query($query);
}
