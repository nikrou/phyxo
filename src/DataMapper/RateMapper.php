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

namespace App\DataMapper;

use Phyxo\EntityManager;
use Phyxo\Conf;
use App\Repository\RateRepository;

class RateMapper
{
    private $em, $conf, $userMapper, $imageMapper;

    public function __construct(EntityManager $em, Conf $conf, UserMapper $userMapper, ImageMapper $imageMapper)
    {
        $this->em = $em;
        $this->conf = $conf;
        $this->userMapper = $userMapper;
        $this->imageMapper = $imageMapper;
    }

    /**
     * Rate a picture by the current user.
     */
    public function ratePicture(int $image_id, float $rate): array
    {
        if (!$this->conf['rate'] || !preg_match('/^[0-9]+$/', $rate) || !in_array($rate, $this->conf['rate_items'])) {
            return [];
        }

        $user_anonymous = $this->userMapper->isGuest();

        if ($user_anonymous && !$this->conf['rate_anonymous']) {
            return [];
        }

        $ip_components = explode('.', $_SERVER['REMOTE_ADDR']);
        if (count($ip_components) > 3) {
            array_pop($ip_components);
        }
        $anonymous_id = implode('.', $ip_components);

        if ($user_anonymous) {
            $save_anonymous_id = isset($_COOKIE['anonymous_rater']) ? $_COOKIE['anonymous_rater'] : $anonymous_id;

            if ($anonymous_id != $save_anonymous_id) { // client has changed his IP address or he's trying to fool us
                $result = $this->em->getRepository(RateRepository::class)->findByUserAndAnonymousId($this->userMapper->getUser()->getId(), $anonymous_id);
                $already_there = $this->em->getConnection()->result2array($result, null, 'element_id');

                if (count($already_there) > 0) {
                    $this->em->getRepository(RateRepository::class)->deleteRates($this->userMapper->getUser()->getId(), $save_anonymous_id, $already_there);
                }

                $this->em->getRepository(RateRepository::class)->updateRate(
                    ['anonymous_id' => $anonymous_id],
                    ['user_id' => $this->userMapper->getUser()->getId(), 'anonymous_id' => $save_anonymous_id]
                );
            } // end client changed ip

            setcookie('anonymous_rater', $anonymous_id, strtotime('+1year'), \Phyxo\Functions\Utils::cookie_path());
        } // end anonymous user

        $this->em->getRepository(RateRepository::class)->deleteRate($this->userMapper->getUser()->getId(), $image_id, $user_anonymous ? $anonymous_id : null);
        $this->em->getRepository(RateRepository::class)->addRate($this->userMapper->getUser()->getId(), $image_id, $anonymous_id, $rate, 'CURRENT_DATE');

        return $this->updateRatingScore($image_id);
    }

    /**
     * Update images.rating_score field.
     * We use a bayesian average (http://en.wikipedia.org/wiki/Bayesian_average) with
     *  C = average number of rates per item
     *  m = global average rate (all rates)
     *
     * @param ?int $element_id if null applies to all
     * @return array (score, average, count) values are null if $element_id is false
     */
    public function updateRatingScore(?int $element_id = null)
    {
        $all_rates_count = 0;
        $all_rates_avg = 0;
        $item_ratecount_avg = 0;
        $by_item = [];

        $result = $this->em->getRepository(RateRepository::class)->calculateRateByElement();
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            $all_rates_count += $row['rcount'];
            $all_rates_avg += $row['rsum'];
            $by_item[$row['element_id']] = $row;
        }

        if ($all_rates_count > 0) {
            $all_rates_avg /= $all_rates_count;
            $item_ratecount_avg = $all_rates_count / count($by_item);
        }

        foreach ($by_item as $id => $rate_summary) {
            $score = ($item_ratecount_avg * $all_rates_avg + $rate_summary['rsum']) / ($item_ratecount_avg + $rate_summary['rcount']);
            $score = round($score, 2);
            if ($id === $element_id) {
                $return = [
                    'score' => $score,
                    'average' => round($rate_summary['rsum'] / $rate_summary['rcount'], 2),
                    'count' => $rate_summary['rcount'],
                ];
            }

            $this->imageMapper->getRepository()->updateRatingScore($id, $score);
        }

        return isset($return) ? $return : ['score' => null, 'average' => null, 'count' => 0];
    }
}
