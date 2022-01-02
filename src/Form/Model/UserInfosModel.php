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

use App\Entity\Language;
use App\Entity\Theme;

class UserInfosModel
{
    private $username;

    private $recent_period;

    private $nb_image_page;

    private $language;

    private $theme;

    private $show_nb_comments;

    private $show_nb_hits;

    private $expand;

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getRecentPeriod()
    {
        return $this->recent_period;
    }

    public function setRecentPeriod($recent_period): self
    {
        $this->recent_period = $recent_period;

        return $this;
    }

    public function getNbImagePage()
    {
        return $this->nb_image_page;
    }

    public function setNbImagePage($nb_image_page): self
    {
        $this->nb_image_page = $nb_image_page;

        return $this;
    }

    public function getTheme(): ?Theme
    {
        return $this->theme;
    }

    public function setTheme(Theme $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    public function getLanguage(): ?Language
    {
        return $this->language;
    }

    public function setLanguage(Language $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getExpand(): bool
    {
        return $this->expand;
    }

    public function setExpand(bool $expand): self
    {
        $this->expand = $expand;

        return $this;
    }

    public function getShowNbComments(): ?bool
    {
        return $this->show_nb_comments;
    }

    public function setShowNbComments(bool $show_nb_comments): self
    {
        $this->show_nb_comments = $show_nb_comments;

        return $this;
    }

    public function getShowNbHits(): ?bool
    {
        return $this->show_nb_hits;
    }

    public function setShowNbHits(bool $show_nb_hits): self
    {
        $this->show_nb_hits = $show_nb_hits;

        return $this;
    }
}
