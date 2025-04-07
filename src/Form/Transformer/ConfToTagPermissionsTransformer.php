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
use App\Enum\TagPermissionEnum;
use App\Enum\UserStatusType;
use App\Form\Model\TagPermissionsModel;
use Phyxo\Conf;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<mixed, TagPermissionsModel>
 */
class ConfToTagPermissionsTransformer implements DataTransformerInterface
{
    /**
     * @param Conf $conf
     */
    public function transform($conf): TagPermissionsModel
    {
        $model = new TagPermissionsModel();
        $model->setTagsPermissionAdd($conf[TagPermissionEnum::ADD->value] ? UserStatusType::from($conf[TagPermissionEnum::ADD->value]) : null);
        $model->setTagsPermissionDelete($conf[TagPermissionEnum::DELETE->value] ? UserStatusType::from($conf[TagPermissionEnum::DELETE->value]) : null);
        $model->setTagsExistingOnly($conf[TagPermissionEnum::EXISTING_TAGS_ONLY->value] ?? false);
        $model->setTagsPublishImmediately($conf[TagPermissionEnum::PUBLISH_IMMEDIATELY->value] ?? false);
        $model->setTagsDeleteImmediately($conf[TagPermissionEnum::DELETE_IMMEDIATELY->value] ?? false);
        $model->setTagsShowPendingAdded($conf[TagPermissionEnum::SHOW_PENDING_ADDED->value] ?? false);
        $model->setTagsShowPendingDeleted($conf[TagPermissionEnum::SHOW_PENDING_DELETED->value] ?? false);

        return $model;
    }

    /**
     * @param TagPermissionsModel $model
     */
    public function reverseTransform(mixed $model): mixed
    {
        return [
            TagPermissionEnum::ADD->value => ['value' => $model->getTagsPermissionAdd() ? $model->getTagsPermissionAdd()->value : '', 'type' => ConfEnum::STRING],
            TagPermissionEnum::DELETE->value => ['value' => $model->getTagsPermissionDelete() ? $model->getTagsPermissionDelete()->value : '', 'type' => ConfEnum::STRING],
            TagPermissionEnum::EXISTING_TAGS_ONLY->value => ['value' => $model->getTagsExistingOnly(), 'type' => ConfEnum::BOOLEAN],
            TagPermissionEnum::PUBLISH_IMMEDIATELY->value => ['value' => $model->getTagsPublishImmediately(), 'type' => ConfEnum::BOOLEAN],
            TagPermissionEnum::DELETE_IMMEDIATELY->value => ['value' => $model->getTagsDeleteImmediately(), 'type' => ConfEnum::BOOLEAN],
            TagPermissionEnum::SHOW_PENDING_ADDED->value => ['value' => $model->getTagsShowPendingAdded(), 'type' => ConfEnum::BOOLEAN],
            TagPermissionEnum::SHOW_PENDING_DELETED->value => ['value' => $model->getTagsShowPendingDeleted(), 'type' => ConfEnum::BOOLEAN],
        ];
    }
}
