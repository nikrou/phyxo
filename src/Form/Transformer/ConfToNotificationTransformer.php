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

namespace App\Form\Transformer;

use App\Enum\ConfEnum;
use App\Form\Model\NotificationModel;
use Phyxo\Conf;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<mixed, NotificationModel>
 */
class ConfToNotificationTransformer implements DataTransformerInterface
{
    /**
     * @param Conf $conf
     */
    public function transform($conf): NotificationModel
    {
        $model = new NotificationModel();
        $model->setNbmSendHtmlMail($conf['nbm_send_html_mail'] ?? false);
        $model->setNbmSendMailAs($conf['nbm_send_mail_as'] ?? null);
        $model->setNbmSendDetailedContent($conf['nbm_send_detailed_content'] ?? false);
        $model->setNbmComplementaryMailContent($conf['nbm_complementary_mail_content'] ?? null);
        $model->setNbmSendRecentPostDates($conf['nbm_send_recent_post_dates'] ?? false);

        return $model;
    }

    /**
     *  @param NotificationModel $model
     */
    public function reverseTransform(mixed $model): mixed
    {
        return [
            'nbm_send_html_mail' => ['value' => $model->getNbmSendHtmlMail(), 'type' => ConfEnum::BOOLEAN],
            'nbm_send_mail_as' => ['value' => $model->getNbmSendMailAs(), 'type' => ConfEnum::STRING],
            'nbm_send_detailed_content' => ['value' => $model->getNbmSendDetailedContent(), 'type' => ConfEnum::BOOLEAN],
            'nbm_complementary_mail_content' => ['value' => $model->getNbmComplementaryMailContent(), 'type' => ConfEnum::STRING],
            'nbm_send_recent_post_dates' => ['value' => $model->getNbmSendRecentPostDates(), 'type' => ConfEnum::BOOLEAN],
        ];
    }
}
