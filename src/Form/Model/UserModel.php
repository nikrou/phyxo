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

use Symfony\Component\Validator\Constraints as Assert;

class UserModel
{
    /**
     * @Assert\NotBlank(message="Please enter your username")
     */
    private $username;

    /**
     * @Assert\NotBlank(message="Please enter your password")
     */
    private $password;


    /**
     * @Assert\Email()
     */
    private $mail_address;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getMailAddress(): ?string
    {
        return $this->mail_address;
    }

    public function setMailAddress(?string $mail_address): self
    {
        $this->mail_address = $mail_address;

        return $this;
    }
}
