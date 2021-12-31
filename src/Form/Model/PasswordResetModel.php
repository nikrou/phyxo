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

class PasswordResetModel
{
    private $new_password;

    public function setNewPassword(string $new_password): self
    {
        $this->new_password = $new_password;

        return $this;
    }

    public function getNewPassword(): string
    {
        return $this->new_password;
    }
}
