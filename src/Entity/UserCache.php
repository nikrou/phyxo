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

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use App\Repository\UserCacheRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserCacheRepository::class)]
class UserCache
{
    final public const ACCESS_IN = 'IN';
    final public const ACCESS_NOT_IN = 'NOT IN';

    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'userCache', cascade: ['persist', 'remove'])]
    private User $user;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $need_update = true;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $cache_update_time = null;

    #[ORM\Column(name: 'forbidden_categories', type: Types::TEXT)]
    private string $forbidden_albums = '[]';

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $nb_total_images = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $last_photo_date = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $nb_available_tags = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $nb_available_comments = 0;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $image_access_type = self::ACCESS_NOT_IN;

    #[ORM\Column(type: Types::TEXT)]
    private string $image_access_list = '[]';

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function isNeedUpdate(): ?bool
    {
        return $this->need_update;
    }

    public function setNeedUpdate(bool $need_update): self
    {
        $this->need_update = $need_update;

        return $this;
    }

    public function getCacheUpdateTime(): ?int
    {
        return $this->cache_update_time;
    }

    public function setCacheUpdateTime(?int $cache_update_time): self
    {
        $this->cache_update_time = $cache_update_time;

        return $this;
    }

    /**
     * @return int[]
     */
    public function getForbiddenAlbums(): array
    {
        return json_decode($this->forbidden_albums, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param int[] $forbidden_albums
     */
    public function setForbiddenAlbums(array $forbidden_albums = []): self
    {
        $this->forbidden_albums = json_encode($forbidden_albums, JSON_THROW_ON_ERROR);

        return $this;
    }

    public function getNbTotalImages(): ?int
    {
        return $this->nb_total_images;
    }

    public function setNbTotalImages(?int $nb_total_images): self
    {
        $this->nb_total_images = $nb_total_images;

        return $this;
    }

    public function getLastPhotoDate(): ?DateTimeInterface
    {
        return $this->last_photo_date;
    }

    public function setLastPhotoDate(?DateTimeInterface $last_photo_date): self
    {
        $this->last_photo_date = $last_photo_date;

        return $this;
    }

    public function getNbAvailableTags(): ?int
    {
        return $this->nb_available_tags;
    }

    public function setNbAvailableTags(?int $nb_available_tags): self
    {
        $this->nb_available_tags = $nb_available_tags;

        return $this;
    }

    public function getNbAvailableComments(): ?int
    {
        return $this->nb_available_comments;
    }

    public function setNbAvailableComments(?int $nb_available_comments): self
    {
        $this->nb_available_comments = $nb_available_comments;

        return $this;
    }

    public function getImageAccessType(): ?string
    {
        return $this->image_access_type;
    }

    public function setImageAccessType(string $image_access_type): self
    {
        $this->image_access_type = $image_access_type;

        return $this;
    }

    /**
     * @return int[]
     */
    public function getImageAccessList(): array
    {
        return json_decode($this->image_access_list, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param int[] $image_access_list
     */
    public function setImageAccessList(array $image_access_list = []): self
    {
        $this->image_access_list = json_encode($image_access_list, JSON_THROW_ON_ERROR);

        return $this;
    }
}
