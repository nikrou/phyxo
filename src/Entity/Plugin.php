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

use App\Enum\ExtensionStateType;
use App\Repository\PluginRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'plugins')]
#[ORM\Entity(repositoryClass: PluginRepository::class)]
class Plugin
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 40)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 25, enumType: ExtensionStateType::class)]
    private ExtensionStateType $state = ExtensionStateType::INACTIVE;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $version = null;

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getState(): ExtensionStateType
    {
        return $this->state;
    }

    public function setState(ExtensionStateType $state): self
    {
        $this->state = $state;

        return $this;
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
}
