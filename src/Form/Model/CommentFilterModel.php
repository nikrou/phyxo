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

use App\Entity\Album;

class CommentFilterModel
{
    private int $page = 0;

    /**
     * @var mixed|string|null
     */
    private $keyword;

    private ?string $author = null;

    /**
     * @var mixed|Album|null
     */
    private $album;

    private array $albums = [];

    /**
     * @var mixed|string|null
     */
    private $since;

    /**
     * @var mixed|string|null
     */
    private $sort_by;

    /**
     * @var mixed|string|null
     */
    private $sort_order;

    /**
     * @var mixed|int|null
     */
    private $items_number;

    public function fromArray(array $defaults = []): self
    {
        if (isset($defaults['keyword'])) {
            $this->keyword = $defaults['keyword'];
        }

        if (isset($defaults['album'])) {
            $this->album = $defaults['album'];
        }

        if (isset($defaults['since'])) {
            $this->since = $defaults['since'];
        }

        if (isset($defaults['sort_by'])) {
            $this->sort_by = $defaults['sort_by'];
        }

        if (isset($defaults['sort_order'])) {
            $this->sort_order = $defaults['sort_order'];
        }

        if (isset($defaults['items_number'])) {
            $this->items_number = $defaults['items_number'];
        }

        return $this;
    }

    public function toArray(): array
    {
        $params = [];

        if (!is_null($this->keyword)) {
            $params['keyword'] = $this->keyword;
        }

        if (count($this->albums) > 0) {
            $params['album_ids'] = $this->albums;
        }

        if (!is_null($this->since)) {
            $params['since'] = $this->since;
        }

        if (!is_null($this->sort_by)) {
            $params['sort_by'] = $this->sort_by;
        }

        if (!is_null($this->sort_order)) {
            $params['sort_order'] = $this->sort_order;
        }

        if (!is_null($this->items_number)) {
            $params['items_number'] = $this->items_number;
        }

        return $params;
    }

    public function toQueryParams(): array
    {
        $params = [];

        if (!is_null($this->keyword)) {
            $params['keyword'] = $this->keyword;
        }

        if (!is_null($this->album)) {
            $params['album'] = $this->album->getId();
        }

        if (!is_null($this->since)) {
            $params['since'] = $this->since;
        }

        if (!is_null($this->sort_by)) {
            $params['sort_by'] = $this->sort_by;
        }

        if (!is_null($this->sort_order)) {
            $params['sort_order'] = $this->sort_order;
        }

        if (!is_null($this->items_number)) {
            $params['items_number'] = $this->items_number;
        }

        return $params;
    }

    public function setPage(int $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setKeyword(string $keyword): self
    {
        $this->keyword = $keyword;

        return $this;
    }

    public function getKeyword(): ?string
    {
        return $this->keyword;
    }

    public function setAuthor(string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAlbum(Album $album): self
    {
        $this->album = $album;

        return $this;
    }

    public function getAlbum(): ?Album
    {
        return $this->album;
    }

    public function setAlbums(array $albums = []): self
    {
        $this->albums = $albums;

        return $this;
    }

    public function getAlbums(): array
    {
        return $this->albums;
    }

    public function setSince(string $since): self
    {
        $this->since = $since;

        return $this;
    }

    public function getSince(): ?string
    {
        return $this->since;
    }

    public function setSortBy(string $sort_by): self
    {
        $this->sort_by = $sort_by;

        return $this;
    }

    public function getSortBy(): ?string
    {
        return $this->sort_by;
    }

    public function setSortOrder(string $sort_order): self
    {
        $this->sort_order = $sort_order;

        return $this;
    }

    public function getSortOrder(): ?string
    {
        return $this->sort_order;
    }

    public function setItemsNumber(int $items_number): self
    {
        $this->items_number = $items_number;

        return $this;
    }

    public function getItemsNumber(): int
    {
        return (int) $this->items_number;
    }
}
