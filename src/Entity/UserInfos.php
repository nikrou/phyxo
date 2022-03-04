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

use App\Repository\UserInfosRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=UserInfosRepository::class)
 * @ORM\Table(name="user_infos")
 */
class UserInfos
{
    private int $nb_total_images;
    /**
     * @var int[] $forbidden_albums
     */
    private array $forbidden_albums = [];
    /**
     * @var int[] $image_access_list
     */
    private array $image_access_list = [];
    private string $image_access_type = 'NOT IN';

    /**
     * @ORM\Id
     * @ORM\OneToOne(targetEntity=User::class, inversedBy="userInfos", cascade={"persist", "remove"})
     * @ORM\JoinColumn(name="user_id", nullable=false)
     * @var User $user
     */
    private $user;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private string $status;

    /**
     * @ORM\Column(type="integer")
     */
    private int $nb_image_page = 15;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $theme;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private string $language;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTimeInterface $registration_date;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $level = 0;

    /**
     * @ORM\Column(type="integer")
     */
    private int $recent_period = 7;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $expand = false;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $show_nb_comments = false;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $show_nb_hits = false;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $enabled_high = true;

    /**
     * @ORM\Column(name="lastmodified", type="datetime", nullable=true)
     */
    private ?\DateTimeInterface $last_modified;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $activation_key;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTimeInterface $activation_key_expire;

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getNbImagePage(): ?int
    {
        return $this->nb_image_page;
    }

    public function setNbImagePage(int $nb_image_page): self
    {
        $this->nb_image_page = $nb_image_page;

        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getRegistrationDate(): ?\DateTimeInterface
    {
        return $this->registration_date;
    }

    public function setRegistrationDate(?\DateTimeInterface $registration_date): self
    {
        $this->registration_date = $registration_date;

        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(?int $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getRecentPeriod(): ?int
    {
        return $this->recent_period;
    }

    public function setRecentPeriod(int $recent_period): self
    {
        $this->recent_period = $recent_period;

        return $this;
    }

    public function setNbTotalImages(int $total): self
    {
        $this->nb_total_images = $total;

        return $this;
    }

    public function getNbTotalImages(): ?int
    {
        return $this->nb_total_images ?? null;
    }

    public function wantExpand(): bool
    {
        return $this->expand;
    }

    public function setExpand(bool $expand): self
    {
        $this->expand = $expand;

        return $this;
    }

    /**
     * @return int[]
     */
    public function getForbiddenAlbums(): array
    {
        return $this->forbidden_albums;
    }

    /**
     * @param int[] $forbidden_albums
     */
    public function setForbiddenAlbums(array $forbidden_albums = []): self
    {
        $this->forbidden_albums = $forbidden_albums;

        return $this;
    }

    /**
     * @return int[]
     */
    public function getImageAccessList(): array
    {
        return $this->image_access_list;
    }

    /**
     * @param int[] $image_access_list
     */
    public function setImageAccessList(array $image_access_list = []): self
    {
        $this->image_access_list = $image_access_list;

        return $this;
    }

    public function getImageAccessType(): string
    {
        return $this->image_access_type;
    }

    public function setImageAccessType(string $image_access_type = 'NOT IN'): self
    {
        $this->image_access_type = $image_access_type;

        return $this;
    }

    public function getShowNbComments(): ?bool
    {
        return $this->show_nb_comments;
    }

    public function setShowNbComments(bool $show_nb_comments): self
    {
        $this->show_nb_comments = $show_nb_comments;

        return $this;
    }

    public function getShowNbHits(): ?bool
    {
        return $this->show_nb_hits;
    }

    public function setShowNbHits(bool $show_nb_hits): self
    {
        $this->show_nb_hits = $show_nb_hits;

        return $this;
    }

    public function hasEnabledHigh(): ?bool
    {
        return $this->enabled_high;
    }

    public function setEnabledHigh(bool $enabled_high): self
    {
        $this->enabled_high = $enabled_high;

        return $this;
    }

    /**
     * @return array{nb_image_page: int, language: string, expand: bool, show_nb_comments: bool, show_nb_hits: bool, recent_period: int, theme: string, enabled_high: bool, level: int}
     */
    public function toArray(): array
    {
        return [
            'nb_image_page' => $this->getNbImagePage(),
            'language' => $this->getLanguage(),
            'expand' => $this->wantExpand(),
            'show_nb_comments' => $this->getShowNbComments(),
            'show_nb_hits' => $this->getShowNbHits(),
            'recent_period' => $this->getRecentPeriod(),
            'theme' => $this->getTheme(),
            'enabled_high' => $this->hasEnabledHigh(),
            'level' => $this->getLevel(),
        ];
    }

    /**
     * @param array{nb_image_page: int, language: string, expand: bool, show_nb_comments: bool, show_nb_hits: bool, recent_period: int, theme: string, enabled_high: bool, level: int} $data
     */
    public function fromArray(array $data): void
    {
        $this->setNbImagePage($data['nb_image_page']);
        $this->setLanguage($data['language']);
        $this->setExpand($data['expand']);
        $this->setShowNbComments($data['show_nb_comments']);
        $this->setShowNbHits($data['show_nb_hits']);
        $this->setRecentPeriod($data['recent_period']);
        $this->setTheme($data['theme']);
        $this->setEnabledHigh($data['enabled_high']);
        $this->setLevel($data['level']);
    }

    public function getLastModified(): ?\DateTimeInterface
    {
        return $this->last_modified;
    }

    public function setLastModified(\DateTimeInterface $last_modified): self
    {
        $this->last_modified = $last_modified;

        return $this;
    }

    public function getActivationKey(): ?string
    {
        return $this->activation_key;
    }

    public function setActivationKey(?string $activation_key): self
    {
        $this->activation_key = $activation_key;

        return $this;
    }

    public function getActivationKeyExpire(): ?\DateTimeInterface
    {
        return $this->activation_key_expire;
    }

    public function setActivationKeyExpire(?\DateTimeInterface $activation_key_expire): self
    {
        $this->activation_key_expire = $activation_key_expire;

        return $this;
    }
}
