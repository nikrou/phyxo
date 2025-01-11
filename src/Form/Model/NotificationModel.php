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

class NotificationModel
{
    private bool $nbm_send_html_mail = false;
    private ?string $nbm_send_mail_as = null;
    private bool $nbm_send_detailed_content = false;
    private ?string $nbm_complementary_mail_content = null;
    private bool $nbm_send_recent_post_dates = false;

    public function getNbmSendHtmlMail(): bool
    {
        return $this->nbm_send_html_mail;
    }

    public function setNbmSendHtmlMail(bool $nbm_send_html_mail): self
    {
        $this->nbm_send_html_mail = $nbm_send_html_mail;

        return $this;
    }

    public function getNbmSendMailAs(): ?string
    {
        return $this->nbm_send_mail_as;
    }

    public function setNbmSendMailAs(?string $nbm_send_mail_as = null): self
    {
        $this->nbm_send_mail_as = $nbm_send_mail_as;

        return $this;
    }

    public function getNbmSendDetailedContent(): bool
    {
        return $this->nbm_send_detailed_content;
    }

    public function setNbmSendDetailedContent(bool $nbm_send_detailed_content): self
    {
        $this->nbm_send_detailed_content = $nbm_send_detailed_content;

        return $this;
    }

    public function getNbmComplementaryMailContent(): ?string
    {
        return $this->nbm_complementary_mail_content;
    }

    public function setNbmComplementaryMailContent(?string $nbm_complementary_mail_content = null): self
    {
        $this->nbm_complementary_mail_content = $nbm_complementary_mail_content;

        return $this;
    }

    public function getNbmSendRecentPostDates(): bool
    {
        return $this->nbm_send_recent_post_dates;
    }

    public function setNbmSendRecentPostDates(bool $nbm_send_recent_post_dates): self
    {
        $this->nbm_send_recent_post_dates = $nbm_send_recent_post_dates;

        return $this;
    }
}
