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

namespace App\Form\Model;

class DisplayConfigurationModel
{
    private bool $menubar_filter_icon;
    private bool $index_new_icon;
    private bool $index_sort_order_input;
    private bool $index_flat_icon;
    private bool $index_posted_date_icon;
    private bool $index_created_date_icon;
    private int $nb_categories_page;
    private bool $picture_metadata_icon;
    private bool $picture_download_icon;
    private bool $picture_favorite_icon;
    private bool $picture_navigation_icons;
    private bool $picture_navigation_thumb;
    private bool $picture_menu;
    private array $picture_informations;

    public function setMenubarFilterIcon(bool $menubar_filter_icon): self
    {
        $this->menubar_filter_icon = $menubar_filter_icon;

        return $this;
    }

    public function getMenubarFilterIcon(): bool
    {
        return $this->menubar_filter_icon;
    }

    public function setIndexNewIcon(bool $index_new_icon): self
    {
        $this->index_new_icon = $index_new_icon;

        return $this;
    }

    public function getIndexNewIcon(): bool
    {
        return $this->index_new_icon;
    }

    public function setIndexSortOrderInput(bool $index_sort_order_input): self
    {
        $this->index_sort_order_input = $index_sort_order_input;

        return $this;
    }

    public function getIndexSortOrderInput(): bool
    {
        return $this->index_sort_order_input;
    }

    public function setIndexFlatIcon(bool $index_flat_icon): self
    {
        $this->index_flat_icon = $index_flat_icon;

        return $this;
    }

    public function getIndexFlatIcon(): bool
    {
        return $this->index_flat_icon;
    }

    public function setIndexPostedDateIcon(bool $index_posted_date_icon): self
    {
        $this->index_posted_date_icon = $index_posted_date_icon;

        return $this;
    }

    public function getIndexPostedDateIcon(): bool
    {
        return $this->index_posted_date_icon;
    }

    public function setIndexCreatedDateIcon(bool $index_created_date_icon): self
    {
        $this->index_created_date_icon = $index_created_date_icon;

        return $this;
    }

    public function getIndexCreatedDateIcon(): bool
    {
        return $this->index_created_date_icon;
    }

    public function setNbCategoriesPage(int $nb_categories_page): self
    {
        $this->nb_categories_page = $nb_categories_page;

        return $this;
    }

    public function getNbCategoriesPage(): int
    {
        return $this->nb_categories_page;
    }

    public function setPictureMetadataIcon(bool $picture_metadata_icon): self
    {
        $this->picture_metadata_icon = $picture_metadata_icon;

        return $this;
    }

    public function getPictureMetadataIcon(): bool
    {
        return $this->picture_metadata_icon;
    }

    public function setPictureDownloadIcon(bool $picture_download_icon): self
    {
        $this->picture_download_icon = $picture_download_icon;

        return $this;
    }

    public function getPictureDownloadIcon(): bool
    {
        return $this->picture_download_icon;
    }

    public function setPictureFavoriteIcon(bool $picture_favorite_icon): self
    {
        $this->picture_favorite_icon = $picture_favorite_icon;

        return $this;
    }

    public function getPictureFavoriteIcon(): bool
    {
        return $this->picture_favorite_icon;
    }

    public function setPictureNavigationIcons(bool $picture_navigation_icons): self
    {
        $this->picture_navigation_icons = $picture_navigation_icons;

        return $this;
    }

    public function getPictureNavigationIcons(): bool
    {
        return $this->picture_navigation_icons;
    }

    public function setPictureNavigationThumb(bool $picture_navigation_thumb): self
    {
        $this->picture_navigation_thumb = $picture_navigation_thumb;

        return $this;
    }

    public function getPictureNavigationThumb(): bool
    {
        return $this->picture_navigation_thumb;
    }

    public function setPictureMenu(bool $picture_menu): self
    {
        $this->picture_menu = $picture_menu;

        return $this;
    }

    public function getPictureMenu(): bool
    {
        return $this->picture_menu;
    }

    public function setPictureInformations(array $picture_informations): self
    {
        $this->picture_informations = $picture_informations;

        return $this;
    }

    public function getPictureInformations(): array
    {
        return $this->picture_informations;
    }

    public function setAuthor(bool $author): self
    {
        $this->picture_informations['author'] = $author;

        return $this;
    }

    public function getAuthor(): bool
    {
        return $this->picture_informations['author'];
    }

    public function setCreatedOn(bool $created_on): self
    {
        $this->picture_informations['created_on'] = $created_on;

        return $this;
    }

    public function getCreatedOn(): bool
    {
        return $this->picture_informations['created_on'];
    }

    public function setPostedOn(bool $posted_on): self
    {
        $this->picture_informations['posted_on'] = $posted_on;

        return $this;
    }

    public function getPostedOn(): bool
    {
        return $this->picture_informations['posted_on'];
    }

    public function setDimensions(bool $dimensions): self
    {
        $this->picture_informations['dimensions'] = $dimensions;

        return $this;
    }

    public function getDimensions(): bool
    {
        return $this->picture_informations['dimensions'];
    }

    public function setFile(bool $file): self
    {
        $this->picture_informations['file'] = $file;

        return $this;
    }

    public function getFile(): bool
    {
        return $this->picture_informations['file'];
    }

    public function setFilesize(bool $filesize): self
    {
        $this->picture_informations['filesize'] = $filesize;

        return $this;
    }

    public function getFilesize(): bool
    {
        return $this->picture_informations['filesize'];
    }

    public function setTags(bool $tags): self
    {
        $this->picture_informations['tags'] = $tags;

        return $this;
    }

    public function getTags(): bool
    {
        return $this->picture_informations['tags'];
    }

    public function setCategories(bool $categories): self
    {
        $this->picture_informations['categories'] = $categories;

        return $this;
    }

    public function getCategories(): bool
    {
        return $this->picture_informations['categories'];
    }

    public function setVisits(bool $visits): self
    {
        $this->picture_informations['visits'] = $visits;

        return $this;
    }

    public function getVisits(): bool
    {
        return $this->picture_informations['visits'];
    }

    public function setRatingScore(bool $rating_score): self
    {
        $this->picture_informations['rating_score'] = $rating_score;

        return $this;
    }

    public function getRatingScore(): bool
    {
        return $this->picture_informations['rating_score'];
    }

    public function setPrivacyLevel(bool $privacy_level): self
    {
        $this->picture_informations['privacy_level'] = $privacy_level;

        return $this;
    }

    public function getPrivacyLevel(): bool
    {
        return $this->picture_informations['privacy_level'];
    }
}
