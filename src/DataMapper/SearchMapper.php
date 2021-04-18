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

use App\Entity\User;
use App\Repository\AlbumRepository;

class SearchMapper
{
    private $imageMapper, $albumRepository;

    public function __construct(ImageMapper $imageMapper, AlbumRepository $albumRepository)
    {
        $this->imageMapper = $imageMapper;
        $this->albumRepository = $albumRepository;
    }

    /**
     * Returns an array of 'items' corresponding to the search id.
     * It can be either a quick search or a regular search.
     */
    public function getSearchResults(array $rules, User $user): array
    {
        if (!isset($rules['q'])) {
            return $this->getRegularSearchResults($rules, $user);
        } else {
            return $this->getQuickSearchResults($rules['q'], $user);
        }
    }

    public function getRegularSearchResults(array $rules, User $user): array
    {
        $items = [];
        $tag_items = [];

        if (isset($rules['fields']['tags'])) {
            foreach ($this->imageMapper->getRepository()->getImageIdsForTags(
                        $user->getUserInfos()->getForbiddenCategories(),
                        $rules['fields']['tags']['words'],
                        $rules['fields']['tags']['mode']) as $image) {
                $tag_items[] = $image->getId();
            }
        }

        if (isset($rules['fields']['cat']) && $rules['fields']['cat']['sub_inc']) {
            $rules['fields']['cat']['words'] = array_merge(array_values($rules['fields']['cat']['words']), $this->albumRepository->getSubcatIds($rules['fields']['cat']['words']));
        }

        foreach ($this->imageMapper->getRepository()->searchImages($user->getUserInfos()->getForbiddenCategories(), $rules) as $image) {
            $items[] = $image->getId();
        }

        if (count($tag_items) > 0) {
            switch ($rules['mode']) {
                case 'AND':
                    if (count($items) === 0) {
                        $items = $tag_items;
                    } else {
                        $items = array_values(array_intersect($items, $tag_items));
                    }
                    break;
                case 'OR':
                    $items = array_unique(array_merge($items, $tag_items));
                    break;
            }
        }

        return $items;
    }

    public function getQuickSearchResults(string $q, User $user): array
    {
        $items = [];

        foreach ($this->imageMapper->getRepository()->qSearchImages($user->getUserInfos()->getForbiddenCategories(), $q) as $image) {
            $items[] = $image->getId();
        }

        return $items;
    }
}
