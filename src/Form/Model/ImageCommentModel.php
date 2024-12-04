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

use App\Entity\Image;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

class ImageCommentModel
{
    private ?string $author = null;
    private ?User $user = null;

    #[Assert\Email(message: 'Please enter a valid mail address')]
    private ?string $mail_address = null;
    private ?Image $image = null;

    #[Assert\NotBlank(message: 'Please enter a message for your comment')]
    private ?string $content = null;

    /**
     * @Assert\URL(message="Please enter a valid Website URL")
     */
    private ?string $website_url = null;
    private ?string $client_ip = null;

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
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

    public function setMailAddress(string $mail_address): self
    {
        $this->mail_address = $mail_address;

        return $this;
    }

    public function getMailAddress(): ?string
    {
        return $this->mail_address;
    }

    public function setImage(Image $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getImage(): Image
    {
        return $this->image;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setWebsiteUrl(string $website_url): self
    {
        $this->website_url = $website_url;

        return $this;
    }

    public function getWebsiteUrl(): ?string
    {
        return $this->website_url;
    }

    public function setClientIp(string $client_ip): self
    {
        $this->client_ip = $client_ip;

        return $this;
    }

    public function getClientIp(): string
    {
        return $this->client_ip;
    }
}
