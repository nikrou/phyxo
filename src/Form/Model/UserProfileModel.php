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

use App\Entity\UserInfos;

class UserProfileModel
{
    private ?string $username = null;

    private ?string $current_password = null;

    private ?string $new_password = null;

    private ?string $mail_address = null;

    private ?UserInfos $user_infos = null;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getCurrentPassword(): ?string
    {
        return $this->current_password;
    }

    public function setCurrentPassword(?string $current_password): self
    {
        $this->current_password = $current_password;

        return $this;
    }

    public function getNewPassword(): ?string
    {
        return $this->new_password;
    }

    public function setNewPassword(?string $new_password): self
    {
        $this->new_password = $new_password;

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

    public function setUserInfos(UserInfos $userInfos): self
    {
        $this->user_infos = $userInfos;

        return $this;
    }

    public function getUserInfos(): UserInfos
    {
        return $this->user_infos;
    }
}
