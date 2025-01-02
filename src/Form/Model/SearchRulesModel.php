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

use DateTime;
use App\Entity\User;

class SearchRulesModel
{
    private ?DateTime $start = null;
    private ?DateTime $end = null;

    /** @var array<string> */
    private array $types = [];
    private ?int $image_id = null;

    /** @var array<int> */
    private array $image_ids = [];
    private ?string $filename = null;
    private string $display_thumbnail;
    private ?User $user = null;

    public function setStart(?DateTime $start): self
    {
        $this->start = $start;

        return $this;
    }

    public function getStart(): ?DateTime
    {
        return $this->start;
    }

    public function setEnd(?DateTime $end): self
    {
        $this->end = $end;

        return $this;
    }

    public function getEnd(): ?DateTime
    {
        return $this->end;
    }

    /**
     * @param array<string> $types
     */
    public function setTypes(array $types): self
    {
        $this->types = $types;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function setImageId(int $image_id): self
    {
        $this->image_id = $image_id;

        return $this;
    }

    public function getImageId(): ?int
    {
        return $this->image_id;
    }

    public function addImageId(int $image_id): self
    {
        $this->image_ids[] = $image_id;

        return $this;
    }

    /**
     * @param array<int> $image_ids
     */
    public function setImageIds(array $image_ids): self
    {
        $this->image_ids = $image_ids;

        return $this;
    }

    /**
     * @return array<int>
     */
    public function getImageIds(): array
    {
        return $this->image_ids;
    }

    public function setFilename(?string $filename): self
    {
        $this->filename = (string) $filename;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setDisplayThumbnail(string $display_thumbnail): self
    {
        $this->display_thumbnail = $display_thumbnail;

        return $this;
    }

    public function getDisplayThumbnail(): string
    {
        return $this->display_thumbnail;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
