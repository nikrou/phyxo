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

use App\Repository\RateRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RateRepository::class)
 * @ORM\Table(name="rate")
 */
class Rate
{
    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="rates")
     * @ORM\JoinColumn(name="user_id", nullable=false)
     */
    private User $user;

    /**
     * @ORM\ManyToOne(targetEntity=Image::class, inversedBy="rates")
     * @ORM\JoinColumn(name="element_id", nullable=false)
     */
    private Image $image;

    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=45)
     */
    private string $anonymous_id;

    /**
     * @ORM\Column(type="integer")
     */
    private int $rate;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTimeInterface $date;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(Image $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getAnonymousId(): ?string
    {
        return $this->anonymous_id;
    }

    public function setAnonymousId(string $anonymous_id): self
    {
        $this->anonymous_id = $anonymous_id;

        return $this;
    }

    public function getRate(): ?int
    {
        return $this->rate;
    }

    public function setRate(int $rate): self
    {
        $this->rate = $rate;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }
}
