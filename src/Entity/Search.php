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
use App\Repository\SearchRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'search')]
#[ORM\Entity(repositoryClass: SearchRepository::class)]
class Search
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $last_seen = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rules = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLastSeen(): ?DateTimeInterface
    {
        return $this->last_seen;
    }

    public function setLastSeen(?DateTimeInterface $last_seen): self
    {
        $this->last_seen = $last_seen;

        return $this;
    }

    public function getRules(): ?string
    {
        return $this->rules;
    }

    public function setRules(?string $rules): self
    {
        $this->rules = $rules;

        return $this;
    }
}
