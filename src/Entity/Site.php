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

use App\Repository\SiteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SiteRepository::class)
 * @ORM\Table(name="sites")
 */
class Site
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", unique=true, length=255)
     */
    private $galleries_url;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGalleriesUrl(): ?string
    {
        return $this->galleries_url;
    }

    public function setGalleriesUrl(string $galleries_url): self
    {
        $this->galleries_url = $galleries_url;

        return $this;
    }
}
