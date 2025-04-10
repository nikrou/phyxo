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

use App\DataMapper\AlbumMapper;
use App\DataMapper\ImageMapper;
use App\DataMapper\SearchMapper;
use App\DataMapper\TagMapper;
use App\Entity\Search;
use App\Enum\PictureSectionType;
use App\Repository\SearchRepository;
use App\Repository\TagRepository;
use App\Security\AppUserService;
use DateTime;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SearchController extends AbstractController
{
    use ThumbnailsControllerTrait;

    /**
     * @param array<string, string> $publicTemplates
     */
    #[Route('/qsearch', name: 'qsearch')]
    public function qsearch(Request $request, SearchRepository $searchRepository, array $publicTemplates): Response
    {
        if (!$request->get('q')) {
            throw $this->createNotFoundException();
        }

        $rules = ['q' => $request->get('q')];
        $search_id = null;
        $search = $searchRepository->findOneBy(['rules' => base64_encode(serialize($rules))]);

        if (!is_null($search)) {
            $search_id = $search->getId();
            $searchRepository->updateLastSeen($search_id);
        } else {
            $search = new Search();
            $search->setRules(base64_encode(serialize($rules)));
            $search->setLastSeen(new DateTime());
            $searchRepository->addSearch($search);

            $search_id = $search->getId();
        }

        return $this->render(sprintf('%s.html.twig', $publicTemplates['search_results']));
    }

    /**
     * @param array<string, string> $publicTemplates
     */
    #[Route('/search', name: 'search')]
    public function search(
        Request $request,
        TagMapper $tagMapper,
        AlbumMapper $albumMapper,
        SearchRepository $searchRepository,
        TranslatorInterface $translator,
        ImageMapper $imageMapper,
        AppUserService $appUserService,
        array $publicTemplates,
    ): Response {
        $tpl_params = [];

        $tpl_params['PAGE_TITLE'] = $translator->trans('Search');

        $available_tags = $tagMapper->getAvailableTags($appUserService->getUser());

        if ($available_tags !== []) {
            usort($available_tags, $tagMapper->alphaCompare(...));
            $tpl_params['TAGS'] = $available_tags;
        }

        // authors
        $authors = [];
        $author_counts = [];
        foreach ($imageMapper->getRepository()->findGroupByAuthor($appUserService->getUser()->getUserInfos()->getForbiddenAlbums()) as $image) {
            if (!isset($author_counts[$image->getAuthor()])) {
                $author_counts[$image->getAuthor()] = 0;
            }

            $author_counts[$image->getAuthor()]++;
        }

        foreach ($author_counts as $author => $counter) {
            $authors[] = [
                'author' => $author,
                'counter' => $counter,
            ];
        }

        $tpl_params['AUTHORS'] = $authors;

        $month_list = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        foreach ($month_list as &$month) {
            $month = $translator->trans($month);
        }

        $month_list[0] = '------------';
        ksort($month_list);
        $tpl_params['month_list'] = $month_list;

        $albums = [];
        foreach ($albumMapper->getRepository()->findAllowedAlbums($appUserService->getUser()->getUserInfos()->getForbiddenAlbums()) as $album) {
            $albums[] = $album;
        }

        $tpl_params = array_merge($tpl_params, $albumMapper->displaySelectAlbumsWrapper($albums, [], 'category_options', true));

        $tpl_params['F_SEARCH_ACTION'] = $this->generateUrl('search');
        $tpl_params['month_list'] = $month_list;

        $rules = [];
        if ($request->isMethod('POST')) {
            if ($request->request->has('search_allwords') && !preg_match('/^\s*$/', $request->request->get('search_allwords'))) {
                $fields = array_intersect($request->request->all('fields'), ['name', 'comment', 'file']);

                $drop_char_match = [
                    '-', '^', '$', ';', '#', '&', '(', ')', '<', '>', '`', "'", '"', '|', ',', '@', '_',
                    '?', '%', '~', '.', '[', ']', '{', '}', ':', '\\', '/', '=', "'", '!', '*',
                ];
                $drop_char_replace = [
                    ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', '', '', ' ', ' ', ' ', ' ', '', ' ',
                    ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', '', ' ', ' ', ' ', ' ', ' ',
                ];

                // Split words
                $rules['fields']['allwords'] = [
                    'words' => array_unique(
                        preg_split(
                            '/\s+/',
                            str_replace(
                                $drop_char_match,
                                $drop_char_replace,
                                $request->request->get('search_allwords')
                            )
                        )
                    ),
                    'mode' => $request->request->get('mode'),
                    'fields' => $fields,
                ];
            }

            if ($request->request->has('tags')) {
                $rules['fields']['tags'] = [
                    'words' => $request->request->all('tags'),
                    'mode' => $request->request->get('tag_mode'),
                ];
            }

            /* @phpstan-ignore-next-line */
            if ($request->request->has('authors') && is_array($request->request->all('authors')) && (is_countable($request->request->all('authors')) ? count($request->request->all('authors')) : 0) > 0) {
                $authors = $request->request->all('authors');
                $rules['fields']['author'] = [
                    'words' => $authors,
                    'mode' => 'OR',
                ];
            }

            if ($request->request->has('cat')) {
                $rules['fields']['cat'] = [
                    'words' => $request->request->all('cat'),
                    'sub_inc' => $request->request->get('subcats-included') == 1,
                ];
            }

            // dates
            $type_date = $request->request->get('date_type');

            if ($request->request->get('start_year')) {
                $rules['fields'][$type_date . '-after'] = [
                    'date' => sprintf(
                        '%d-%02d-%02d',
                        $request->request->get('start_year'),
                        $request->request->get('start_month') != 0 ? $request->request->get('start_month') : '01',
                        $request->request->get('start_day') != 0 ? $request->request->get('start_day') : '01'
                    ),
                    'inc' => true,
                ];
            }

            if ($request->request->get('end_year')) {
                $rules['fields'][$type_date . '-before'] = [
                    'date' => sprintf(
                        '%d-%02d-%02d',
                        $request->request->get('end_year'),
                        $request->request->get('end_month') != 0 ? $request->request->get('end_month') : '12',
                        $request->request->get('end_day') != 0 ? $request->request->get('end_day') : '31'
                    ),
                    'inc' => true,
                ];
            }

            if ($rules !== []) {
                // default search mode : each clause must be respected
                $rules['mode'] = 'AND';

                // register search rules in database, then they will be available on thumbnails page and picture page.
                $encoded_rules = base64_encode(serialize($rules));
                $search = $searchRepository->findOneBy(['rules' => $encoded_rules]);

                if (!is_null($search)) {
                    $searchRepository->updateLastSeen($search->getId());
                } else {
                    $search = new Search();
                    $search->setRules($encoded_rules);
                    $search->setLastSeen(new DateTime());
                    $searchRepository->addSearch($search);
                }

                return $this->redirectToRoute('search_results', ['search_id' => $search->getId()]);
            } else {
                $tpl_params['errors'][] = $translator->trans('Empty query. No criteria has been entered.');
            }

            if ($request->request->has('start_day')) {
                $tpl_params['START_DAY_SELECTED'] = $request->request->get('start_day');
            }

            if ($request->request->has('start_month')) {
                $tpl_params['START_MONTH_SELECTED'] = $request->request->get('start_month');
            }

            if ($request->request->has('end_day')) {
                $tpl_params['END_DAY_SELECTED'] = $request->request->get('end_day');
            }

            if ($request->request->has('end_month')) {
                $tpl_params['END_MONTH_SELECTED'] = $request->request->get('end_month');
            }
        }

        $tpl_params['F_SEARCH_ACTION'] = $this->generateUrl('search');

        return $this->render(sprintf('%s.html.twig', $publicTemplates['search']), $tpl_params);
    }

    /**
     * @param array<string, string> $publicTemplates
     */
    #[Route('/search/{search_id}/{start}', name: 'search_results', defaults: ['start' => 0], requirements: ['search_id' => '\d+', 'start' => '\d+'])]
    public function searchResults(
        Request $request,
        SearchMapper $searchMapper,
        AlbumMapper $albumMapper,
        ImageMapper $imageMapper,
        Conf $conf,
        SearchRepository $searchRepository,
        int $search_id,
        TranslatorInterface $translator,
        RouterInterface $router,
        AppUserService $appUserService,
        array $publicTemplates,
        int $start = 0,
    ): Response {
        $tpl_params = [];

        $tpl_params['PAGE_TITLE'] = $translator->trans('Search results');
        $tpl_params['TITLE'] = $translator->trans('Search results');
        $tpl_params['U_SEARCH_RULES'] = $this->generateUrl('search_rules', ['search_id' => $search_id]);

        $search = $searchRepository->findOneBy(['id' => $search_id]);
        if (is_null($search) || empty($search->getRules())) {
            throw $this->createNotFoundException();
        }
        $rules = unserialize(base64_decode($search->getRules()));

        $tpl_params['items'] = $searchMapper->getSearchResults($rules, $appUserService->getUser());
        $search_results = [];

        /* @phpstan-ignore-next-line */
        if (!empty($search_results['qsearch_details'])) {
            $cats = [];
            /* @phpstan-ignore-next-line */
            if (!empty($search_results['qsearch_details']['matching_cats_no_image'])) {
                /** @phpstan-ignore-next-line */
                $cats = array_merge($cats, $search_results['qsearch_details']['matching_cats_no_image']);
            }

            /* @phpstan-ignore-next-line */
            if ($search_results['qsearch_details']['matching_cats']) {
                /** @phpstan-ignore-next-line */
                $cats = array_merge($cats, $search_results['qsearch_details']['matching_cats']);
            }

            if ($cats !== []) {
                usort($cats, Utils::nameCompare(...));
                $hints = [];
                foreach ($cats as $cat) {
                    $hints[] = $albumMapper->getAlbumDisplayName([$cat]);
                }

                $tpl_params['category_search_results'] = $hints;
            }

            /* @phpstan-ignore-next-line */
            if (!empty($search_results['qsearch_details']['matching_tags'])) {
                /* @phpstan-ignore-next-line */
                foreach ($search_results['qsearch_details']['matching_tags'] as $tag) {
                    $tag['URL'] = $this->generateUrl('images_by_tags', ['tag_ids' => Utils::tagToUrl($tag)]);
                    $tpl_params['tag_search_results'] = $tag;
                }
            }
        }

        if ($tpl_params['items'] !== []) {
            $nb_image_page = $appUserService->getUser()->getUserInfos()->getNbImagePage();
            $tpl_params['thumb_navbar'] = $this->defineNavigation(
                $router,
                'search_results',
                ['search_id' => $search_id],
                count($tpl_params['items']),
                $start,
                $nb_image_page,
                $conf['paginate_pages_around']
            );
            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    $search_id,
                    PictureSectionType::SEARCH,
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    $start
                )
            );

            /* @phpstan-ignore-next-line */
            if (!empty($search_results['qsearch_details']) && !empty($search_results['qsearch_details']['unmatched_terms'])) {
                /* @phpstan-ignore-next-line */
                $tpl_params['no_search_results'] = $search_results['qsearch_details']['unmatched_terms'];
            }

        /* @phpstan-ignore-next-line */
        } elseif (!empty($search_results['qsearch_details']) && !empty($search_results['qsearch_details']['q'])) {
            /* @phpstan-ignore-next-line */
            $tpl_params['no_search_results'] = $search_results['qsearch_details']['q'];
        }

        if ($request->cookies->has('album_view')) {
            $tpl_params['album_view'] = $request->cookies->get('album_view');
        }

        $tpl_params['START_ID'] = $start;

        return $this->render(sprintf('%s.html.twig', $publicTemplates['album']), $tpl_params);
    }

    /**
     * @param array<string, string> $publicTemplates
     */
    #[Route('/search_rules/{search_id}', name: 'search_rules', requirements: ['search_id' => '\d+'])]
    public function searchRules(
        AlbumMapper $albumMapper,
        TagRepository $tagRepository,
        SearchRepository $searchRepository,
        array $publicTemplates,
        TranslatorInterface $translator,
        int $search_id,
    ): Response {
        $tpl_params = [];

        $tpl_params['PAGE_TITLE'] = $translator->trans('Search rules');

        $rules = [];
        $search = $searchRepository->findOneBy(['id' => $search_id]);
        if (!is_null($search) && !empty($search->getRules())) {
            $rules = unserialize(base64_decode($search->getRules()));
        }

        if (isset($rules['q'])) {
            $tpl_params['search_words'] = $rules['q'];
        } else {
            $tpl_params['INTRODUCTION'] = $rules['mode'] === 'OR' ? $translator->trans('At least one listed rule must be satisfied.') : $translator->trans('Each listed rule must be satisfied.');
        }

        if (isset($rules['fields']['allwords'])) {
            $tpl_params['search_words'] = $translator->trans('searched words : {words}', ['words' => implode(', ', $rules['fields']['allwords']['words'])]);
        }

        if (isset($rules['fields']['tags'])) {
            $tpl_params['SEARCH_TAGS_MODE'] = $rules['fields']['tags']['mode'];

            $tpl_params['search_tags'] = [];
            foreach ($tagRepository->findBy(['id' => $rules['fields']['tags']['words']]) as $tag) {
                $tpl_params['search_tags'][] = $tag->getName();
            }
        }

        if (isset($rules['fields']['author'])) {
            $tpl_params['search_words'] = $translator->trans('author(s) : {authors}', ['authors' => implode(', ', array_map('strip_tags', $rules['fields']['author']['words']))]);
        }

        if (isset($rules['fields']['cat'])) {
            $album_ids = [];
            if ($rules['fields']['cat']['sub_inc']) {
                // searching all the albums id of sub-albums
                $album_ids = $albumMapper->getRepository()->getSubcatIds($rules['fields']['cat']['words']);
            } else {
                $album_ids = $rules['fields']['cat']['words'];
            }

            $albums = [];
            foreach ($albumMapper->getRepository()->findBy(['id' => $album_ids]) as $album) {
                $albums[] = $album;
            }

            usort($albums, AlbumMapper::globalRankCompare(...));

            foreach ($albums as $album) {
                $tpl_params['search_categories'] = $albumMapper->getAlbumsDisplayName($album->getUppercats(), 'album');
            }
        }

        foreach (['date_available', 'date_creation'] as $datefield) {
            if ($datefield === 'date_available') {
                $lang_items = [
                    'date' => $translator->trans('posted on %s'),
                    'period' => $translator->trans('posted between %s (%s) and %s (%s)'),
                    'after' => $translator->trans('posted after %s (%s)'),
                    'before' => $translator->trans('posted before %s (%s)'),
                ];
            } else { // date_creation
                $lang_items = [
                    'date' => $translator->trans('created on %s'),
                    'period' => $translator->trans('created between %s (%s) and %s (%s)'),
                    'after' => $translator->trans('created after %s (%s)'),
                    'before' => $translator->trans('created before %s (%s)'),
                ];
            }

            $keys = [
                'date' => $datefield,
                'after' => $datefield . '-after',
                'before' => $datefield . '-before',
            ];

            if (isset($rules['fields'][$keys['date']])) {
                $tpl_params[strtoupper($datefield)] = sprintf($lang_items['date'], $rules['fields'][$keys['date']]->format('l D M Y'));
            } elseif (isset($rules['fields'][$keys['before']], $rules['fields'][$keys['after']])) {
                $tpl_params[strtoupper($datefield)] = sprintf(
                    $lang_items['period'],
                    $rules['fields'][$keys['after']]['date']->format('l D M Y'),
                    $rules['fields'][$keys['after']]['inc'] ? $translator->trans('included') : $translator->trans('excluded'),
                    $rules['fields'][$keys['before']]['date']->format('l D M Y'),
                    $rules['fields'][$keys['before']]['inc'] ? $translator->trans('included') : $translator->trans('excluded')
                );
            } elseif (isset($rules['fields'][$keys['before']])) {
                $tpl_params[strtoupper($datefield)] = sprintf(
                    $lang_items['before'],
                    $rules['fields'][$keys['before']]['date']->format('l D M Y'),
                    $rules['fields'][$keys['before']]['inc'] ? $translator->trans('included') : $translator->trans('excluded')
                );
            } elseif (isset($rules['fields'][$keys['after']])) {
                $tpl_params[strtoupper($datefield)] = sprintf(
                    $lang_items['after'],
                    $rules['fields'][$keys['after']]['date']->format('l D M Y'),
                    $rules['fields'][$keys['after']]['inc'] ? $translator->trans('included') : $translator->trans('excluded')
                );
            }
        }

        return $this->render(sprintf('%s.html.twig', $publicTemplates['search_rules']), $tpl_params);
    }
}
