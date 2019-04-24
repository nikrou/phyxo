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

namespace Phyxo\Functions;

use App\Repository\RateRepository;
use App\Repository\ImageRepository;

class Rate
{
    /**
     * Rate a picture by the current user.
     */
    public static function rate_picture(int $image_id, float $rate): array
    {
        global $conf, $user, $conn, $userMapper;

        if (!isset($rate) || !$conf['rate'] || !preg_match('/^[0-9]+$/', $rate) || !in_array($rate, $conf['rate_items'])) {
            return false;
        }

        $user_anonymous = $userMapper->isClassicUser();

        if ($user_anonymous and !$conf['rate_anonymous']) {
            return false;
        }

        $ip_components = explode('.', $_SERVER['REMOTE_ADDR']);
        if (count($ip_components) > 3) {
            array_pop($ip_components);
        }
        $anonymous_id = implode('.', $ip_components);

        if ($user_anonymous) {
            $save_anonymous_id = isset($_COOKIE['anonymous_rater']) ? $_COOKIE['anonymous_rater'] : $anonymous_id;

            if ($anonymous_id != $save_anonymous_id) { // client has changed his IP address or he's trying to fool us
                $result = (new RateRepository($conn))->findByUserAndAnonymousId($user['id'], $anonymous_id);
                $already_there = $conn->result2array($result, null, 'element_id');

                if (count($already_there) > 0) {
                    (new RateRepository($conn))->deleteRates($user['id'], $save_anonymous_id, $already_there);
                }

                (new RateRepository($conn))->updateRate(
                    ['anonymous_id' => $anonymous_id],
                    ['user_id' => $user['id'], 'anonymous_id' => $save_anonymous_id]
                );
            } // end client changed ip

            setcookie('anonymous_rater', $anonymous_id, strtotime('+1year'), \Phyxo\Functions\Utils::cookie_path());
        } // end anonymous user

        (new RateRepository($conn))->deleteRate($user['id'], $image_id, $user_anonymous ? $anonymous_id : null);

        (new RateRepository($conn))->addRate($user['id'], $image_id, $anonymous_id, $rate, 'now()');

        return self::update_rating_score($image_id);
    }

    /**
     * Update images.rating_score field.
     * We use a bayesian average (http://en.wikipedia.org/wiki/Bayesian_average) with
     *  C = average number of rates per item
     *  m = global average rate (all rates)
     *
     * @param int|false $element_id if false applies to all
     * @return array (score, average, count) values are null if $element_id is false
     */
    public static function update_rating_score($element_id = false)
    {
        global $conn;

        if (($alt_result = \Phyxo\Functions\Plugin::trigger_change('update_rating_score', false, $element_id)) !== false) {
            return $alt_result;
        }

        $all_rates_count = 0;
        $all_rates_avg = 0;
        $item_ratecount_avg = 0;
        $by_item = [];

        $result = (new RateRepository($conn))->calculateRateByElement();
        while ($row = $conn->db_fetch_assoc($result)) {
            $all_rates_count += $row['rcount'];
            $all_rates_avg += $row['rsum'];
            $by_item[$row['element_id']] = $row;
        }

        if ($all_rates_count > 0) {
            $all_rates_avg /= $all_rates_count;
            $item_ratecount_avg = $all_rates_count / count($by_item);
        }

        $updates = [];
        foreach ($by_item as $id => $rate_summary) {
            $score = ($item_ratecount_avg * $all_rates_avg + $rate_summary['rsum']) / ($item_ratecount_avg + $rate_summary['rcount']);
            $score = round($score, 2);
            if ($id == $element_id) {
                $return = [
                    'score' => $score,
                    'average' => round($rate_summary['rsum'] / $rate_summary['rcount'], 2),
                    'count' => $rate_summary['rcount'],
                ];
            }
            $updates[] = ['id' => $id, 'rating_score' => $score];
        }
        (new ImageRepository($conn))->massUpdates(
            [
                'primary' => ['id'],
                'update' => ['rating_score']
            ],
            $updates
        );

        return isset($return) ? $return : ['score' => null, 'average' => null, 'count' => 0];
    }
}
