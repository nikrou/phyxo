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

use App\Enum\UserPrivacyLevelType;
use App\Enum\UserStatusType;
use App\Repository\UserInfosRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * @phpstan-type UserInfosArray array{nb_image_page: int, language: ?string, expand: bool, show_nb_comments: bool, show_nb_hits: bool,
 *  recent_period: int, theme: ?string, enabled_high: bool, level: UserPrivacyLevelType}
 */
#[ORM\Table(name: 'user_infos')]
#[ORM\Entity(repositoryClass: UserInfosRepository::class)]
class UserInfos
{
    final public const int DEFAULT_NB_IMAGE_PAGE = 15;
    final public const int DEFAULT_RECENT_PERIOD = 7;
    final public const bool DEFAULT_SHOW_NB_COMMENTS = false;
    final public const bool DEFAULT_SHOW_NB_HITS = false;
    final public const bool DEFAULT_ENABLED_HIGH = false;
    private int $nb_total_images;

    /**
     * @var int[]
     */
    private array $forbidden_albums = [];

    /**
     * @var int[]
     */
    private array $image_access_list = [];
    private string $image_access_type = 'NOT IN';

    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'userInfos', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: UserStatusType::class)]
    private UserStatusType $status;

    #[ORM\Column(type: Types::INTEGER)]
    private int $nb_image_page = self::DEFAULT_NB_IMAGE_PAGE;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $theme;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $language;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $registration_date = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, enumType: UserPrivacyLevelType::class)]
    private ?UserPrivacyLevelType $level = UserPrivacyLevelType::DEFAULT;

    #[ORM\Column(type: Types::INTEGER)]
    private int $recent_period = self::DEFAULT_RECENT_PERIOD;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $expand = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $show_nb_comments = self::DEFAULT_SHOW_NB_COMMENTS;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $show_nb_hits = self::DEFAULT_SHOW_NB_HITS;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled_high = self::DEFAULT_ENABLED_HIGH;

    #[ORM\Column(name: 'lastmodified', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $last_modified = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $activation_key = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $activation_key_expire = null;

    public function getStatus(): ?UserStatusType
    {
        return $this->status;
    }

    public function getStatusValue(): ?string
    {
        return $this->status->value;
    }

    public function setStatus(UserStatusType $status): self
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

    public function getNbImagePage(): int
    {
        return $this->nb_image_page ?? self::DEFAULT_NB_IMAGE_PAGE;
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

    public function getRegistrationDate(): ?DateTimeInterface
    {
        return $this->registration_date;
    }

    public function setRegistrationDate(?DateTimeInterface $registration_date): self
    {
        $this->registration_date = $registration_date;

        return $this;
    }

    public function getLevel(): UserPrivacyLevelType
    {
        return $this->level;
    }

    public function setLevel(UserPrivacyLevelType $level = UserPrivacyLevelType::DEFAULT): self
    {
        $this->level = $level;

        return $this;
    }

    public function getRecentPeriod(): int
    {
        return $this->recent_period ?? self::DEFAULT_RECENT_PERIOD;
    }

    public function setRecentPeriod(int $recent_period = self::DEFAULT_RECENT_PERIOD): self
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

    public function getShowNbComments(): bool
    {
        return $this->show_nb_comments ?? self::DEFAULT_SHOW_NB_COMMENTS;
    }

    public function setShowNbComments(bool $show_nb_comments = self::DEFAULT_SHOW_NB_COMMENTS): self
    {
        $this->show_nb_comments = $show_nb_comments;

        return $this;
    }

    public function getShowNbHits(): bool
    {
        return $this->show_nb_hits ?? self::DEFAULT_SHOW_NB_HITS;
    }

    public function setShowNbHits(bool $show_nb_hits = self::DEFAULT_SHOW_NB_HITS): self
    {
        $this->show_nb_hits = $show_nb_hits;

        return $this;
    }

    public function hasEnabledHigh(): bool
    {
        return $this->enabled_high ?? self::DEFAULT_ENABLED_HIGH;
    }

    public function setEnabledHigh(bool $enabled_high = self::DEFAULT_ENABLED_HIGH): self
    {
        $this->enabled_high = $enabled_high;

        return $this;
    }

    /**
     * @return UserInfosArray
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
     * @param UserInfosArray $data
     */
    public function fromArray(array $data): void
    {
        $this->setNbImagePage($data['nb_image_page']);
        $this->setLanguage((string) $data['language']);
        $this->setExpand($data['expand']);
        $this->setShowNbComments($data['show_nb_comments']);
        $this->setShowNbHits($data['show_nb_hits']);
        $this->setRecentPeriod($data['recent_period']);
        $this->setTheme((string) $data['theme']);
        $this->setEnabledHigh($data['enabled_high']);
        $this->setLevel($data['level']);
    }

    public function getLastModified(): ?DateTimeInterface
    {
        return $this->last_modified;
    }

    public function setLastModified(DateTimeInterface $last_modified): self
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

    public function getActivationKeyExpire(): ?DateTimeInterface
    {
        return $this->activation_key_expire;
    }

    public function setActivationKeyExpire(?DateTimeInterface $activation_key_expire): self
    {
        $this->activation_key_expire = $activation_key_expire;

        return $this;
    }
}
