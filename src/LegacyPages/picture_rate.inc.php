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

use App\Repository\RateRepository;

if ($conf['rate']) {
    $rate_summary = ['count' => 0, 'score' => $picture['current']['rating_score'], 'average' => null];
    if (null != $rate_summary['score']) {
        $calculated_rate = (new RateRepository($conn))->calculateRateSummary($picture['current']['id']);
        $rate_summary['count'] = $calculated_rate['count'];
        $rate_summary['average'] = $calculated_rate['average'];
    }
    $template->assign('rate_summary', $rate_summary);

    $user_rate = null;
    $anonymous_id = null;
    if ($conf['rate_anonymous'] || $userMapper->isClassicUser()) {
        if ($rate_summary['count'] > 0) {
            if (!$userMapper->isClassicUser()) {
                $ip_components = explode('.', $_SERVER['REMOTE_ADDR']);
                if (count($ip_components) > 3) {
                    array_pop($ip_components);
                }
                $anonymous_id = implode('.', $ip_components);
            }

            $result = (new RateRepository($conn))->findByUserIdAndElementIdAndAnonymousId(
                $user['id'],
                $page['image_id'],
                $anonymous_id
            );
            if ($conn->db_num_rows($result) > 0) {
                $row = $conn->db_fetch_assoc($result);
                $user_rate = $row['rate'];
            }
        }

        $template->assign(
            'rating',
            [
                'F_ACTION' => \Phyxo\Functions\URL::add_url_params(
                    $url_self,
                    ['action' => 'rate']
                ),
                'USER_RATE' => $user_rate,
                'marks' => $conf['rate_items']
            ]
        );
    }
}
