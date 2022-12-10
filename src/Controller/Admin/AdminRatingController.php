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

namespace App\Controller\Admin;

use App\DataMapper\ImageMapper;
use App\DataMapper\UserMapper;
use App\Repository\RateRepository;
use App\Repository\UserRepository;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\DerivativeParams;
use Phyxo\Image\ImageStandardParams;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminRatingController extends AbstractController
{
    private TranslatorInterface $translator;

    protected function setTabsheet(string $section = 'photos'): TabSheet
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('photos', $this->translator->trans('Photos', [], 'admin'), $this->generateUrl('admin_rating'));
        $tabsheet->add('users', $this->translator->trans('Users', [], 'admin'), $this->generateUrl('admin_rating_users'));
        $tabsheet->select($section);

        return $tabsheet;
    }

    public function photos(
        Request $request,
        TranslatorInterface $translator,
        UserMapper $userMapper,
        UserRepository $userRepository,
        RateRepository $rateRepository,
        RouterInterface $router,
        int $start = 0
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $navbar_params = [];
        $elements_per_page = 10;
        if ($request->get('display') && is_numeric($request->get('display'))) {
            $elements_per_page = $request->get('display');
            $navbar_params['display'] = $elements_per_page;
        }

        $order_by_index = 0;
        if ($request->get('order_by') && is_numeric($request->get('order_by'))) {
            $order_by_index = $request->get('order_by');
            $navbar_params['order_by'] = $order_by_index;
        }

        $operator_user_filter = null;
        $guest_id = 0;
        if ($request->get('users')) {
            $guest_id = $userMapper->getDefaultUser()->getId();

            if ($request->get('users') === 'user') {
                $operator_user_filter = '!=';
            } elseif ($request->get('users') === 'guest') {
                $operator_user_filter = '=';
            }
            $navbar_params['users'] = $request->get('users');
        }

        $users = [];
        foreach ($userRepository->findAll() as $user) {
            $users[$user->getId()] = $user->getUsername();
        }

        $nb_images = $rateRepository->countImagesRatedForUser($guest_id, $operator_user_filter);

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_rating', ['start' => $start]);
        $tpl_params['DISPLAY'] = $elements_per_page;
        $tpl_params['NB_ELEMENTS'] = $nb_images;

        $available_order_by = [
            [$translator->trans('Rate date', [], 'admin'), 'recently_rated DESC'],
            [$translator->trans('Rating score', [], 'admin'), 'score DESC'],
            [$translator->trans('Average rate', [], 'admin'), 'avg_rates DESC'],
            [$translator->trans('Number of rates', [], 'admin'), 'nb_rates DESC'],
            [$translator->trans('Sum of rates', [], 'admin'), 'sum_rates DESC'],
            [$translator->trans('Filename', [], 'admin'), 'file DESC'],
            [$translator->trans('Creation date', [], 'admin'), 'date_creation DESC'],
            [$translator->trans('Post date', [], 'admin'), 'date_available DESC'],
        ];

        for ($i = 0; $i < count($available_order_by); $i++) {
            $tpl_params['order_by_options'][] = $available_order_by[$i][0];
        }
        $tpl_params['order_by_options_selected'] = [$order_by_index];

        $user_options = [
            'all' => $translator->trans('All', [], 'admin'),
            'user' => $translator->trans('Users', [], 'admin'),
            'guest' => $translator->trans('Guests', [], 'admin'),
        ];

        $tpl_params['user_options'] = $user_options;
        $tpl_params['user_options_selected'] = [$request->get('users')];

        $tpl_params['images'] = [];
        foreach ($rateRepository->getRatePerImage($guest_id, $available_order_by[$order_by_index][1], $elements_per_page, $operator_user_filter, $start) as $image) {
            $pathBasename = (($pos = strrpos((string) $image['path'], '.')) === false) ? $image['path'] : substr((string) $image['path'], 0, $pos);
            $extension = (($pos = strrpos((string) $image['path'], '.')) === false) ? '' : substr((string) $image['path'], $pos + 1);

            $thumbnail_src = $this->generateUrl(
                'admin_media',
                ['path' => $pathBasename, 'derivative' => DerivativeParams::derivative_to_url(ImageStandardParams::IMG_THUMB), 'image_extension' => $extension]
            );
            $image_url = $this->generateUrl('admin_photo', ['image_id' => $image['id']]);

            $rates = $rateRepository->findBy(['image' => $image['id']]);
            $nb_rates = count($rates);

            $tpl_image = [
                'id' => $image['id'],
                'U_THUMB' => $thumbnail_src,
                'U_URL' => $image_url,
                'SCORE_RATE' => $image['score'],
                'AVG_RATE' => round($image['avg_rates'], 2),
                'SUM_RATE' => $image['sum_rates'],
                'NB_RATES' => (int) $image['nb_rates'],
                'NB_RATES_TOTAL' => (int) $nb_rates,
                'FILE' => $image['file'],
                'rates' => []
            ];

            foreach ($rates as $rate) {
                if (isset($users[$rate->getUser()->getId()])) {
                    $user_rate = $users[$rate->getUser()->getId()];
                } else {
                    $user_rate = '? ' . $rate->getUser()->getId();
                }
                if ($rate->getAnonymousId()) {
                    $user_rate .= '(' . $rate->getAnonymousId() . ')';
                }

                $tpl_image['rates'][] = [
                    'USER' => $user_rate,
                    'user_id' => $rate->getUser()->getId(),
                    'md5sum' => md5($rate->getUser()->getId() . $rate->getImage()->getId() . $rate->getAnonymousId()),
                    'element_id' => $rate->getImage()->getId(),
                    'anonymous_id' => $rate->getAnonymousId(),
                    'rate' => $rate->getRate(),
                    'date' => $rate->getDate()
                ];
            }
            $tpl_params['images'][] = array_merge($tpl_image, $image);
        }

        $tpl_params['navbar'] = Utils::createNavigationBar($router, 'admin_rating', $navbar_params, $nb_images, $start, $elements_per_page);

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_rating');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_rating');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Rating', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('photos');
        $tpl_params['WS_RATES_DELETE'] = $this->generateUrl('ws') . '?method=pwg.rates.delete';

        return $this->render('rating_photos.html.twig', $tpl_params);
    }

    public function users(
        Request $request,
        Conf $conf,
        UserMapper $userMapper,
        ImageStandardParams $image_std_params,
        TranslatorInterface $translator,
        UserRepository $userRepository,
        ImageMapper $imageMapper,
        RateRepository $rateRepository
    ): Response {
        $tpl_params = [];
        $this->translator = $translator;

        $filter_min_rates = 2;
        if ($request->get('f_min_rates') && is_numeric($request->get('f_min_rates'))) {
            $filter_min_rates = $request->get('f_min_rates');
        }

        $consensus_top_number = $conf['top_number'];
        if ($request->get('consensus_top_number') && is_numeric($request->get('consensus_top_number'))) {
            $consensus_top_number = $request->get('consensus_top_number');
        }

        // build users
        $users_by_id = [];
        foreach ($userRepository->findAll() as $user) {
            $users_by_id[$user->getId()] = [
                'name' => $user->getUsername(),
                'anon' => $userMapper->isClassicUser()
            ];
        }

        $by_user_rating_model = ['rates' => []];
        foreach ($conf['rate_items'] as $rate) {
            $by_user_rating_model['rates'][$rate] = [];
        }

        // by user aggregation
        $image_ids = [];
        $by_user_ratings = [];
        foreach ($rateRepository->findAll() as $rate) {
            if (!isset($users_by_id[$rate->getUser()->getId()])) {
                $users_by_id[$rate->getUser()->getId()] = ['name' => '???' . $rate->getUser()->getId(), 'anon' => false];
            }
            $usr = $users_by_id[$rate->getUser()->getId()];
            if ($usr['anon']) {
                $user_key = $usr['name'] . '(' . $rate->getAnonymousId() . ')';
            } else {
                $user_key = $usr['name'];
            }
            $rating = &$by_user_ratings[$user_key];
            if (is_null($rating)) {
                $rating = $by_user_rating_model;
                $rating['uid'] = $rate->getUser()->getId();
                $rating['aid'] = $usr['anon'] ? $rate->getAnonymousId() : '';
                $rating['last_date'] = $rating['first_date'] = $rate->getDate();
                $rating['md5sum'] = md5($rating['uid'] . $rating['aid']);
            } else {
                $rating['first_date'] = $rate->getDate();
            }

            $rating['rates'][$rate->getRate()][] = [
                'id' => $rate->getImage()->getId(),
                'date' => $rate->getDate(),
            ];
            $image_ids[$rate->getImage()->getId()] = 1;
            unset($rating);
        }

        // get image tn urls
        $image_urls = [];
        if (count($image_ids) > 0) {
            $d_params = $image_std_params->getByType(ImageStandardParams::IMG_SQUARE);
            foreach ($imageMapper->getRepository()->findBy(['id' => array_keys($image_ids)]) as $image) {
                $derivative = new DerivativeImage($image, $d_params, $image_std_params);
                $image_urls[$image->getId()] = [
                    'tn' => $this->generateUrl('admin_media', [
                        'path' => $image->getPathBasename(), 'derivative' => $derivative->getUrlType(), 'image_extension' => $image->getExtension()
                    ]),
                    'page' => $this->generateUrl('picture', ['image_id' => $image->getId(), 'type' => 'file', 'element_id' => $image->getFile()]),
                ];
            }
        }

        //all image averages
        $all_img_sum = [];
        foreach ($rateRepository->calculateAverageByImage() as $rate) {
            $all_img_sum[(int)$rate['image']] = ['avg' => (float)$rate['avg']];
        }

        $best_rated = [];
        foreach ($imageMapper->getRepository()->findBestRatedImages($consensus_top_number) as $image) {
            $best_rated[] = $image->getId();
        }

        // by user stats
        foreach ($by_user_ratings as $id => &$rating) {
            $c = 0;
            $s = 0;
            $ss = 0;
            $consensus_dev = 0;
            $consensus_dev_top = 0;
            $consensus_dev_top_count = 0;
            foreach ($rating['rates'] as $rate => $rates) {
                $ct = is_countable($rates) ? count($rates) : 0;
                $c += $ct;
                $s += $ct * $rate;
                $ss += $ct * $rate * $rate;
                foreach ($rates as $id_date) {
                    $dev = abs($rate - $all_img_sum[$id_date['id']]['avg']);
                    $consensus_dev += $dev;
                    if (isset($best_rated[$id_date['id']])) {
                        $consensus_dev_top += $dev;
                        $consensus_dev_top_count++;
                    }
                }
            }

            $consensus_dev /= $c;
            if ($consensus_dev_top_count) {
                $consensus_dev_top /= $consensus_dev_top_count;
            }

            $var = ($ss - $s * $s / $c) / $c;
            $rating += [
                'id' => $id,
                'count' => $c,
                'avg' => $s / $c,
                'cv' => $s == 0 ? -1 : sqrt($var) / ($s / $c), // http://en.wikipedia.org/wiki/Coefficient_of_variation
                'cd' => $consensus_dev,
                'cdtop' => $consensus_dev_top_count ? $consensus_dev_top : '',
            ];
        }

        $by_user_ratings = array_filter($by_user_ratings, function($rating) use ($filter_min_rates) {
            if ($rating['count'] > $filter_min_rates) {
                return $rating;
            }
        });

        $order_by_index = 4;
        if ($request->get('order_by') && is_numeric($request->get('order_by'))) {
            $order_by_index = $request->get('order_by');
        }

        $available_order_by = [
            [$translator->trans('Average rate', [], 'admin'), 'avg_compare'],
            [$translator->trans('Number of rates', [], 'admin'), 'count_compare'],
            [$translator->trans('Variation', [], 'admin'), 'cv_compare'],
            [$translator->trans('Consensus deviation', [], 'admin'), 'consensus_dev_compare'],
            [$translator->trans('Last', [], 'admin'), 'last_rate_compare'],
        ];

        for ($i = 0; $i < count($available_order_by); $i++) {
            $tpl_params['order_by_options'][] = $available_order_by[$i][0];
        }

        $tpl_params['order_by_options_selected'] = [$order_by_index];
        $x = uasort($by_user_ratings, [$this, $available_order_by[$order_by_index][1]]);

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_rating_users');
        $tpl_params['F_MIN_RATES'] = $filter_min_rates;
        $tpl_params['CONSENSUS_TOP_NUMBER'] = $consensus_top_number;
        $tpl_params['available_rates'] = $conf['rate_items'];
        $tpl_params['ratings'] = $by_user_ratings;
        $tpl_params['image_urls'] = $image_urls;
        $tpl_params['TN_WIDTH'] = $image_std_params->getByType(ImageStandardParams::IMG_SQUARE)->sizing->ideal_size[0];

        $tpl_params['WS_RATES_DELETE'] = $this->generateUrl('ws') . '?method=pwg.rates.delete';
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_rating');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_rating');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Rating', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('users');

        return $this->render('rating_users.html.twig', $tpl_params);
    }

    /** @phpstan-ignore-next-line */ // @FIX: define return type
    protected function avg_compare(array $a, array $b): int
    {
        $d = $a['avg'] - $b['avg'];

        return ($d == 0) ? 0 : ($d < 0 ? -1 : 1);
    }

    /** @phpstan-ignore-next-line */ // @FIX: define return type
    protected function count_compare(array $a, array $b): int
    {
        $d = $a['count'] - $b['count'];

        return ($d == 0) ? 0 : ($d < 0 ? -1 : 1);
    }

    /** @phpstan-ignore-next-line */ // @FIX: define return type
    protected function cv_compare(array $a, array $b): int
    {
        $d = $b['cv'] - $a['cv']; //desc

        return ($d == 0) ? 0 : ($d < 0 ? -1 : 1);
    }

    /** @phpstan-ignore-next-line */ // @FIX: define return type
    protected function consensus_dev_compare(array $a, array $b): int
    {
        $d = $b['cd'] - $a['cd']; //desc

        return ($d == 0) ? 0 : ($d < 0 ? -1 : 1);
    }

    /** @phpstan-ignore-next-line */ // @FIX: define return type
    protected function last_rate_compare(array $a, array $b): int
    {
        return -strcmp((string) $a['last_date'], (string) $b['last_date']);
    }
}
