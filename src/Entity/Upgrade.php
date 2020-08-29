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

use App\Repository\UpgradeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=UpgradeRepository::class)
 * @ORM\Table(name="upgrade")
 */
class Upgrade
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=40)
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $applied;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $description;

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getApplied(): ?\DateTimeInterface
    {
        return $this->applied;
    }

    public function setApplied(\DateTimeInterface $applied): self
    {
        $this->applied = $applied;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
