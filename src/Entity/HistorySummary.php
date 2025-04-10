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

use App\Repository\HistorySummaryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'history_summary')]
#[ORM\Entity(repositoryClass: HistorySummaryRepository::class)]
class HistorySummary
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::INTEGER)]
    private int $nb_pages;

    #[ORM\Column(type: Types::INTEGER)]
    private int $year;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $month = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $day = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $hour = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNbPages(): ?int
    {
        return $this->nb_pages;
    }

    public function setNbPages(int $nb_pages): self
    {
        $this->nb_pages = $nb_pages;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(int $month): self
    {
        $this->month = $month;

        return $this;
    }

    public function getDay(): ?int
    {
        return $this->day;
    }

    public function setDay(int $day): self
    {
        $this->day = $day;

        return $this;
    }

    public function getHour(): ?int
    {
        return $this->hour;
    }

    public function setHour(int $hour): self
    {
        $this->hour = $hour;

        return $this;
    }
}
