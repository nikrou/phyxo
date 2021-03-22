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

namespace App\Events;

use Symfony\Contracts\EventDispatcher\Event;

class GroupEvent extends Event
{
    private $group, $category, $image_url, $mail_content;

    public function __construct(int $group, array $category, string $image_url, string $mail_content)
    {
        $this->group = $group;
        $this->category = $category;
        $this->image_url = $image_url;
        $this->mail_content = $mail_content;
    }

    public function getGroup()
    {
        return $this->group;
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function getImageUrl()
    {
        return $this->image_url;
    }

    public function getMailContent()
    {
        return $this->mail_content;
    }
}
