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

use App\Repository\UserMailNotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'user_mail_notification')]
#[ORM\Entity(repositoryClass: UserMailNotificationRepository::class)]
class UserMailNotification
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: User::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private User $user;

    #[ORM\Column(type: 'string', length: 16, nullable: true, unique: true)]
    private ?string $check_key = '';

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $last_send = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCheckKey(): ?string
    {
        return $this->check_key;
    }

    public function setCheckKey(?string $check_key): self
    {
        $this->check_key = $check_key;

        return $this;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getLastSend(): ?\DateTimeInterface
    {
        return $this->last_send;
    }

    public function setLastSend(\DateTimeInterface $last_send): self
    {
        $this->last_send = $last_send;

        return $this;
    }
}
