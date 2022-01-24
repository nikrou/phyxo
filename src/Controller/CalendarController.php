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

namespace App\Controller;

use App\Repository\ImageRepository;
use App\Security\AppUserService;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Phyxo\Image\ImageStandardParams;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class CalendarController extends CommonController
{
    public function index(
        Request $request,
        ImageRepository $imageRepository,
        string $date_type,
        AppUserService $appUserService,
        Conf $conf
    ): Response {
        $tpl_params = [];
        $tpl_params['date_type'] = $date_type;
        $tpl_params['number_of_images'] = 0;

        foreach ($imageRepository->countImagesByYear($date_type, $appUserService->getUser()->getUserInfos()->getForbiddenCategories()) as $rowYear) {
            if (is_null($rowYear['year'])) {
                continue;
            }

            $tpl_params['number_of_images'] += $rowYear['nb_images'];
            if (isset($tpl_params['years'][$rowYear['year']])) {
                $tpl_params['years'][$rowYear['year']]['nb_images'] += $rowYear['nb_images'];
            } else {
                $tpl_params['years'][$rowYear['year']] = [
                    'label' => $rowYear['year'],
                    'nb_images' => $rowYear['nb_images'],
                    'url' => $this->generateUrl('calendar_by_year', ['date_type' => $date_type, 'year' => $rowYear['year']])
                ];
            }
        }

        $years = array_keys($tpl_params['years']);
        for ($year = $years[0];$year > $years[count($years) - 1];$year--) {
            if (!isset($tpl_params['years'][$year])) {
                $tpl_params['years'][$year] = [
                    'label' => $year,
                    'nb_images' => 0
                ];
            }
        }
        krsort($tpl_params['years']);

        foreach ($imageRepository->findOneImagePerYear(array_keys($tpl_params['years']), $date_type, $appUserService->getUser()->getUserInfos()->getForbiddenCategories()) as $image) {
            $tpl_params['years'][$image->getDateByType($date_type)->format('Y')]['image'] = $image;
        }

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('calendar.html.twig', $tpl_params);
    }

    public function byYear(
        Request $request,
        ImageRepository $imageRepository,
        string $date_type,
        AppUserService $appUserService,
        Conf $conf,
        int $year
    ): Response {
        $tpl_params = [];
        $tpl_params['number_of_images'] = 0;
        $tpl_params['date_type'] = $date_type;
        $tpl_params['year'] = $year;

        $tpl_params['months'] = array_fill(1, 12, ['nb_images' => 0]);

        $intl_date_formatter = new \IntlDateFormatter($appUserService->getUser()->getUserInfos()->getLanguage(), \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, null, null, 'MMM');
        for ($month = 1;$month <= 12;$month++) {
            $tpl_params['months'][$month]['label'] = $intl_date_formatter->format(new \DateTime(sprintf('2022-%d-01', $month)));
        }

        $monthsWithPhotos = [];
        foreach ($imageRepository->countImagesByMonth($year, $date_type, $appUserService->getUser()->getUserInfos()->getForbiddenCategories()) as $rowMonth) {
            $tpl_params['number_of_images'] += $rowMonth['nb_images'];
            $monthsWithPhotos[] = $this->formatDatePart($rowMonth['month']);
            $tpl_params['months'][$rowMonth['month']]['nb_images'] = $rowMonth['nb_images'];
            $tpl_params['months'][$rowMonth['month']]['url'] = $this->generateUrl(
                'calendar_by_month',
                [
                    'date_type' => $date_type,
                    'year' => $year,
                    'month' => $this->formatDatePart($rowMonth['month'])
                ]
            );
        }

        foreach ($imageRepository->findOneImagePerMonth($year, $monthsWithPhotos, $date_type, $appUserService->getUser()->getUserInfos()->getForbiddenCategories()) as $image) {
            $tpl_params['months'][$image->getDateByType($date_type)->format('n')]['image'] = $image;
        }

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('calendar_by_year.html.twig', $tpl_params);
    }

    public function byMonth(
        Request $request,
        ImageRepository $imageRepository,
        AppUserService $appUserService,
        Conf $conf,
        string $date_type,
        int $year,
        int $month
    ): Response {
        $tpl_params = [];
        $tpl_params['date_type'] = $date_type;
        $tpl_params['year'] = $year;
        $tpl_params['month'] = $this->formatDatePart($month);
        $tpl_params['month_date'] = new \DateTime("${year}-${month}-01");
        $tpl_params['number_of_images'] = 0;

        $intl_date_formatter = new \IntlDateFormatter($appUserService->getUser()->getUserInfos()->getLanguage(), \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, null, null, 'MMMM');
        $tpl_params['month_label'] = $intl_date_formatter->format($tpl_params['month_date']);

        $tpl_params['days'] = array_fill(1, (int) $tpl_params['month_date']->format('t'), ['nb_images' => 0]);

        $daysWithPhotos = [];
        foreach ($imageRepository->countImagesByDay($year, $month, $date_type, $appUserService->getUser()->getUserInfos()->getForbiddenCategories()) as $rowDay) {
            $tpl_params['number_of_images'] += $rowDay['nb_images'];

            $daysWithPhotos[] = $this->formatDatePart($rowDay['day']);
            $tpl_params['days'][$rowDay['day']]['nb_images'] = $rowDay['nb_images'];
            $tpl_params['days'][$rowDay['day']]['url'] = $this->generateUrl(
                'calendar_by_day',
                [
                    'date_type' => $date_type,
                    'year' => $year,
                    'month' => $this->formatDatePart($month),
                    'day' => $this->formatDatePart((int) $rowDay['day'])
                ]
            );
        }

        foreach ($imageRepository->findOneImagePerDay($year, $month, $daysWithPhotos, $date_type, $appUserService->getUser()->getUserInfos()->getForbiddenCategories()) as $image) {
            $tpl_params['days'][$image->getDateByType($date_type)->format('j')]['image'] = $image;
        }

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('calendar_by_month.html.twig', $tpl_params);
    }

    public function byDay(
        Request $request,
        ImageRepository $imageRepository,
        Conf $conf,
        ImageStandardParams $image_std_params,
        AppUserService $appUserService,
        RouterInterface $router,
        string $date_type,
        int $year,
        int $month,
        int $day,
        $start = 0
    ): Response {
        $tpl_params = [];
        $tpl_params['date_type'] = $date_type;
        $tpl_params['year'] = $year;
        $tpl_params['month'] = $this->formatDatePart($month);
        $tpl_params['day'] = $this->formatDatePart($day);
        $tpl_params['current_day'] = new \DateTime("${year}-${month}-${day}");

        $intl_date_formatter = new \IntlDateFormatter($appUserService->getUser()->getUserInfos()->getLanguage(), \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, null, null, 'MMMM');
        $tpl_params['month_label'] = $intl_date_formatter->format($tpl_params['current_day']);

        $thumbnails = [];
        foreach ($imageRepository->findImagesPerDate($tpl_params['current_day'], $date_type, $appUserService->getUser()->getUserInfos()->getForbiddenCategories()) as $image) {
            $tpl_thumbnail = $image->toArray();
            $tpl_thumbnail['image'] = $image;
            $tpl_thumbnail['URL'] = $this->generateUrl(
                'picture_from_calendar',
                [
                    'image_id' => $image->getId(), 'date_type' => $date_type, 'year' => $year, 'month' => $this->formatDatePart($month), 'day' => $this->formatDatePart($day)
                ]
            );
            $tpl_thumbnail['TN_ALT'] = '';
            $tpl_thumbnail['TN_TITLE'] = '';

            $thumbnails[] = $tpl_thumbnail;
        }

        $nb_image_page = $appUserService->getUser()->getUserInfos()->getNbImagePage();

        if (count($thumbnails) > $nb_image_page) {
            $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                $router,
                'calendar_by_day',
                ['date_type' => $date_type, 'year' => $year, 'month' => $this->formatDatePart($month), 'day' => $this->formatDatePart($day)],
                count($thumbnails),
                $start,
                $nb_image_page,
                $conf['paginate_pages_around']
            );
        }

        $tpl_params['number_of_images'] = count($thumbnails);
        $tpl_params['thumbnails'] = array_slice($thumbnails, $start, min(count($thumbnails), $nb_image_page));

        $tpl_params['derivative_params'] = $image_std_params->getByType(ImageStandardParams::IMG_SQUARE);
        $tpl_params['START_ID'] = $start;

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('calendar_by_day.html.twig', $tpl_params);
    }

    protected function formatDatePart(int $date_part): string
    {
        return str_pad((string) $date_part, 2, '0', STR_PAD_LEFT);
    }
}
