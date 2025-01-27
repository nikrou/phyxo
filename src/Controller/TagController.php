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

use Symfony\Component\HttpFoundation\Request;
use Phyxo\MenuBar;
use Phyxo\Conf;
use Phyxo\Functions\Language;
use App\DataMapper\TagMapper;
use App\Repository\TagRepository;
use App\DataMapper\ImageMapper;
use App\Entity\Tag;
use App\Enum\PictureSectionType;
use App\Security\AppUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TagController extends AbstractController
{
    use ThumbnailsControllerTrait;

    /**
     * @param array<string, string> $publicTemplates
     */
    #[Route(
        '/tags/{display_mode}',
        name: 'tags',
        defaults: ['display_mode' => 'letters'],
        requirements: ['display_mode' => 'cloud|letters']
    )]
    public function list(Request $request, AppUserService $appUserService, TagMapper $tagMapper, Conf $conf, MenuBar $menuBar, TranslatorInterface $translator): Response
    {
        $tpl_params = [];

        $tpl_params['PAGE_TITLE'] = $translator->trans('Tags');

        $display_mode = $conf['tags_default_display_mode'];
        if ($request->get('display_mode')) {
            $display_mode = $request->get('display_mode');
        }

        foreach (['cloud', 'letters'] as $mode) {
            $tpl_params['U_' . strtoupper($mode)] = $this->generateUrl('tags', ['display_mode' => $mode]);
        }

        $tpl_params['display_mode'] = $display_mode;

        // find all tags available for the current user
        $tags = $tagMapper->getAvailableTags($appUserService->getUser());

        if ($display_mode === 'letters') {
            // we want tags diplayed in alphabetic order
            usort($tags, $tagMapper->alphaCompare(...));

            $current_letter = null;
            $nb_tags = count($tags);
            $current_column = 1;
            $current_tag_idx = 0;

            $letter = ['tags' => [], 'CHANGE_COLUMN' => false];

            foreach ($tags as $tag) {
                $tag_letter = mb_strtoupper(mb_substr(Language::transliterate($tag->getName()), 0, 1, 'utf-8'), 'utf-8');

                if ($current_tag_idx == 0) {
                    $current_letter = $tag_letter;
                    $letter['TITLE'] = $tag_letter;
                }

                //lettre precedente differente de la lettre suivante
                if ($tag_letter !== $current_letter) {
                    if ($current_column < $conf['tag_letters_column_number'] && $current_tag_idx > $current_column * $nb_tags / $conf['tag_letters_column_number']) {
                        $letter['CHANGE_COLUMN'] = true;
                        $current_column++;
                    }

                    $letter['TITLE'] = $current_letter;

                    $tpl_params['letters'][] = $letter;

                    $current_letter = $tag_letter;
                    $letter = ['tags' => []];
                }

                $letter['tags'][] = array_merge(
                    $tag->toArray(),
                    [
                        'URL' => $this->generateUrl('images_by_tags', ['tag_ids' => $tag->toUrl()])
                    ]
                );

                $current_tag_idx++;
            }

            // flush last letter
            if ($letter['tags'] !== []) {
                unset($letter['CHANGE_COLUMN']);
                $letter['TITLE'] = $current_letter;
                $tpl_params['letters'][] = $letter;
            }
        } else {
            // we want only the first most represented tags, so we sort them by counter
            // and take the first tags
            usort($tags, $tagMapper->counterCompare(...));
            $tags = array_slice($tags, 0, $conf['full_tag_cloud_items_number']);

            // depending on its counter and the other tags counter, each tag has a level
            $tags = $tagMapper->addLevelToTags($tags);

            // we want tags diplayed in alphabetic order
            usort($tags, $tagMapper->alphaCompare(...));

            // display sorted tags
            foreach ($tags as $tag) {
                $tpl_params['tags'][] = array_merge(
                    $tag->toArray(),
                    [
                        'URL' => $this->generateUrl('images_by_tags', ['tag_ids' => $tag->toUrl()])
                    ]
                );
            }
        }

        $menuBar->setRoute('tags');

        return $this->render('tags.html.twig', $tpl_params);
    }

    /**
     * @param array<string, string> $publicTemplates
     */
    #[Route(
        '/tags/{tag_ids}/{start}',
        name: 'images_by_tags',
        defaults: ['start' => 0],
        requirements: ['start' => '\d+', 'tag_ids' => '.+']
    )]
    public function imagesByTags(
        Request $request,
        ImageMapper $imageMapper,
        string $tag_ids,
        TranslatorInterface $translator,
        TagRepository $tagRepository,
        Conf $conf,
        MenuBar $menuBar,
        RouterInterface $router,
        AppUserService $appUserService,
        array $publicTemplates,
        int $start = 0
    ): Response {
        $tpl_params = [];

        if ($request->cookies->has('album_view')) {
            $tpl_params['album_view'] = $request->cookies->get('album_view');
        }

        $requested_tag_ids = array_map(
            fn ($tag): string => substr((string) $tag, 0, strpos((string) $tag, '-')),
            explode('/', $tag_ids)
        );

        $tpl_params['tags'] = [];
        foreach ($tagRepository->findBy(['id' => $requested_tag_ids]) as $tag) {
            $tpl_params['tags'][] = $tag;
        }

        $tpl_params['TITLE'] = $this->getTagsContentTitle($translator, $tpl_params['tags']);

        $tpl_params['items'] = [];
        foreach ($imageMapper->getRepository()->getImageIdsForTags($appUserService->getUser()->getUserInfos()->getForbiddenAlbums(), $requested_tag_ids) as $image) {
            $tpl_params['items'][] = $image->getId();
        }

        if ($tpl_params['items'] !== []) {
            $nb_image_page = $appUserService->getUser()->getUserInfos()->getNbImagePage();

            $tpl_params['thumb_navbar'] = $this->defineNavigation(
                $router,
                'images_by_tags',
                ['tag_ids' => $tag_ids],
                count($tpl_params['items']),
                $start,
                $nb_image_page,
                $conf['paginate_pages_around']
            );

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    $tag_ids,
                    PictureSectionType::TAGS,
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    $start
                )
            );

            $menuBar->setCurrentImages(array_slice($tpl_params['items'], $start, $nb_image_page));
        }

        $menuBar->setRoute('images_by_tags');
        $menuBar->setCurrentTags($tpl_params['tags']);

        if ($request->cookies->has('album_view')) {
            $tpl_params['album_view'] = $request->cookies->get('album_view');
        }

        $tpl_params['START_ID'] = $start;

        return $this->render(sprintf('%s.html.twig', $publicTemplates['album']), $tpl_params);
    }

    /**
     * Returns the breadcrumb to be displayed above thumbnails on tag page.
     *
     * @param Tag[] $tags
     */
    protected function getTagsContentTitle(TranslatorInterface $translator, array $tags = []): string
    {
        $title = '<li class="breadcrumb-item">';
        $title .= '<a href="' . $this->generateUrl('tags') . '" title="' . $translator->trans('display available tags') . '">';
        $title .= $translator->trans('number_of_tags', ['count' => count($tags)]);
        $title .= '</a>&nbsp;';
        $counter = count($tags);

        for ($i = 0; $i < $counter; $i++) {
            $title .= $i > 0 ? ' + ' : '';
            $title .= '<a href="' . $this->generateUrl('images_by_tags', ['tag_ids' => $tags[$i]->toUrl()]) . '"';
            $title .= ' title="' . $translator->trans('display photos linked to this tag') . '">';
            $title .= $tags[$i]->getName();
            $title .= '</a>';

            if (count($tags) > 2) {
                $other_tags = $tags;
                unset($other_tags[$i]);
                $remove_url = $this->generateUrl(
                    'images_by_tags',
                    ['tag_ids' => implode('/', array_map(fn (Tag $tag): string => $tag->toUrl(), $other_tags))]
                );

                $title .= '<a href="' . $remove_url . '" title="';
                $title .= $translator->trans('remove this tag from the list');
                $title .= '"><i class="fa fa-remove"></i>';
                $title .= '</a>';
            }
        }

        return $title . '</li>';
    }
}
