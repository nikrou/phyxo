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

use App\Enum\ConfEnum;
use Doctrine\DBAL\Types\Types;
use App\Repository\ConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'config')]
#[ORM\Entity(repositoryClass: ConfigRepository::class)]
class Config
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 40)]
    private string $param;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $value = null;

    #[ORM\Column(type: Types::STRING, length: 15, nullable: true, enumType: ConfEnum::class)]
    private ?ConfEnum $type = ConfEnum::STRING;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $comment = null;

    public function getParam(): ?string
    {
        return $this->param;
    }

    public function setParam(string $param): self
    {
        $this->param = $param;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getType(): ConfEnum
    {
        return $this->type;
    }

    public function setType(ConfEnum $type = ConfEnum::STRING): self
    {
        $this->type = $type;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }
}
