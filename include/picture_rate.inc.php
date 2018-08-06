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
 * This file is included by the picture page to manage rates
 *
 */

if ($conf['rate']) {
    $rate_summary = array('count' => 0, 'score' => $picture['current']['rating_score'], 'average' => null);
    if (null != $rate_summary['score']) {
        $query = 'SELECT COUNT(rate) AS count,ROUND(AVG(rate),2) AS average FROM ' . RATE_TABLE;
        $query .= ' WHERE element_id = ' . $conn->db_real_escape_string($picture['current']['id']);
        list($rate_summary['count'], $rate_summary['average']) = $conn->db_fetch_row($conn->db_query($query));
    }
    $template->assign('rate_summary', $rate_summary);

    $user_rate = null;
    if ($conf['rate_anonymous'] or $services['users']->isAuthorizeStatus(ACCESS_CLASSIC)) {
        if ($rate_summary['count'] > 0) {
            $query = 'SELECT rate FROM ' . RATE_TABLE;
            $query .= ' WHERE element_id = ' . $conn->db_real_escape_string($page['image_id']);
            $query .= ' AND user_id = ' . $conn->db_real_escape_string($user['id']);

            if (!$services['users']->isAuthorizeStatus(ACCESS_CLASSIC)) {
                $ip_components = explode('.', $_SERVER['REMOTE_ADDR']);
                if (count($ip_components) > 3) {
                    array_pop($ip_components);
                }
                $anonymous_id = implode('.', $ip_components);
                $query .= ' AND anonymous_id = \'' . $anonymous_id . '\'';
            }

            $result = $conn->db_query($query);
            if ($conn->db_num_rows($result) > 0) {
                $row = $conn->db_fetch_assoc($result);
                $user_rate = $row['rate'];
            }
        }

        $template->assign(
            'rating',
            array(
                'F_ACTION' => \Phyxo\Functions\URL::add_url_params(
                    $url_self,
                    array('action' => 'rate')
                ),
                'USER_RATE' => $user_rate,
                'marks' => $conf['rate_items']
            )
        );
    }
}
