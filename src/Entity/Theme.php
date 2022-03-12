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

use App\Repository\ThemeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ThemeRepository::class)
 * @ORM\Table(name="themes")
 */
 class Theme
 {
     /**
      * @ORM\Id()
      * @ORM\Column(type="string", length=40)
      */
     private string $id;

     /**
      * @ORM\Column(type="string", length=64, nullable=true)
      */
     private ?string $version;

     /**
      * @ORM\Column(type="string", length=64)
      */
     private string $name;

     public function setId(string $id): self
     {
         $this->id = $id;

         return $this;
     }

     public function getId(): ?string
     {
         return $this->id;
     }

     public function getVersion(): ?string
     {
         return $this->version;
     }

     public function setVersion(?string $version): self
     {
         $this->version = $version;

         return $this;
     }

     public function getName(): ?string
     {
         return $this->name;
     }

     public function setName(string $name): self
     {
         $this->name = $name;

         return $this;
     }
 }
