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

use App\Entity\Rate;
use Phyxo\EntityManager;
use Phyxo\Conf;
use App\Repository\RateRepository;
use Phyxo\Functions\Utils;

class RateMapper
{
    private $em, $conf, $userMapper, $imageMapper, $rateRepository;

    public function __construct(EntityManager $em, Conf $conf, UserMapper $userMapper, ImageMapper $imageMapper, RateRepository $rateRepository)
    {
        $this->em = $em;
        $this->conf = $conf;
        $this->userMapper = $userMapper;
        $this->imageMapper = $imageMapper;
        $this->rateRepository = $rateRepository;
    }

    public function getRepository(): RateRepository
    {
        return $this->rateRepository;
    }

    /**
     * Rate a picture by the current user.
     */
    public function ratePicture(int $image_id, int $note, string $anonymous_id): array
    {
        if (!$this->conf['rate'] || !preg_match('/^[0-9]+$/', $note) || !in_array($note, $this->conf['rate_items'])) {
            return [];
        }

        $user_anonymous = $this->userMapper->isGuest();

        if ($user_anonymous && !$this->conf['rate_anonymous']) {
            return [];
        }

        if ($user_anonymous) {
            $save_anonymous_id = isset($_COOKIE['anonymous_rater']) ? $_COOKIE['anonymous_rater'] : $anonymous_id;

            if ($anonymous_id !== $save_anonymous_id) { // client has changed his IP address or he's trying to fool us
                $rate = $this->getRepository()->findOneBy([
                    'user' => $this->userMapper->getUser()->getId(),
                    'image' => $image_id,
                    'anonymous_id' => $anonymous_id
                ]);
                if (!is_null($rate)) {
                    $this->getRepository()->delete($rate);
                }

                $this->getRepository()->updateAnonymousIdField($anonymous_id, $this->userMapper->getUser()->getId(), $save_anonymous_id);
            }

            setcookie('anonymous_rater', $anonymous_id, strtotime('+1year'), Utils::cookie_path());
        }

        $this->getRepository()->deleteImageRateForUser($this->userMapper->getUser()->getId(), $image_id, $user_anonymous ? $anonymous_id : null);
        $image = $this->imageMapper->getRepository()->find($image_id);
        $rate = new Rate();
        $rate->setUser($this->userMapper->getUser());
        $rate->setImage($image);
        $rate->setAnonymousId($anonymous_id);
        $rate->setDate(new \DateTime());
        $rate->setRate($note);
        $this->getRepository()->addOrUpdateRate($rate);

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

        foreach ($this->getRepository()->calculateRateByImage() as $rate) {
            $all_rates_count += $rate['rcount'];
            $all_rates_avg += $rate['rsum'];
            $by_item[$rate['image']] = $rate;
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
