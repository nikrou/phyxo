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

use App\Repository\CommentRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'comments')]
#[ORM\Entity(repositoryClass: CommentRepository::class)]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $date;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $author = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Email(message: "The email '{{ value }}' is not a valid email.")]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $anonymous_id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Url(message: "The url '{{ value }}' is not a valid url")]
    private ?string $website_url = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $validated = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $validation_date = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(name: 'author_id', nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Image::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false)]
    private Image $image;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getAnonymousId(): ?string
    {
        return $this->anonymous_id;
    }

    public function setAnonymousId(?string $anonymous_id): self
    {
        $this->anonymous_id = $anonymous_id;

        return $this;
    }

    public function getWebsiteUrl(): ?string
    {
        return $this->website_url;
    }

    public function setWebsiteUrl(?string $website_url): self
    {
        $this->website_url = $website_url;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function isValidated(): ?bool
    {
        return $this->validated;
    }

    public function setValidated(?bool $validated): self
    {
        $this->validated = $validated;

        return $this;
    }

    public function getValidationDate(): ?DateTimeInterface
    {
        return $this->validation_date;
    }

    public function setValidationDate(?DateTimeInterface $validation_date): self
    {
        $this->validation_date = $validation_date;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(?Image $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function isPending(): bool
    {
        return $this->validated === false;
    }
}
