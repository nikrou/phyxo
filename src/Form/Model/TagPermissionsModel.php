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

use App\Enum\UserStatusType;

class TagPermissionsModel
{
    private ?UserStatusType $tags_permission_add = null;
    private ?UserStatusType $tags_permission_delete = null;
    private bool $tags_existing_only = false;
    private bool $tags_publish_immediately = false;
    private bool $tags_delete_immediately = false;
    private bool $tags_show_pending_added = false;
    private bool $tags_show_pending_deleted = false;

    public function getTagsPermissionAdd(): ?UserStatusType
    {
        return $this->tags_permission_add;
    }

    public function setTagsPermissionAdd(?UserStatusType $tags_permission_add = null): self
    {
        $this->tags_permission_add = $tags_permission_add;

        return $this;
    }

    public function getTagsPermissionDelete(): ?UserStatusType
    {
        return $this->tags_permission_delete;
    }

    public function setTagsPermissionDelete(?UserStatusType $tags_permission_delete = null): self
    {
        $this->tags_permission_delete = $tags_permission_delete;

        return $this;
    }

    public function getTagsExistingOnly(): bool
    {
        return $this->tags_existing_only;
    }

    public function setTagsExistingOnly(bool $tags_existing_only): self
    {
        $this->tags_existing_only = $tags_existing_only;

        return $this;
    }

    public function getTagsPublishImmediately(): bool
    {
        return $this->tags_publish_immediately;
    }

    public function setTagsPublishImmediately(bool $tags_publish_immediately): self
    {
        $this->tags_publish_immediately = $tags_publish_immediately;

        return $this;
    }

    public function getTagsDeleteImmediately(): bool
    {
        return $this->tags_delete_immediately;
    }

    public function setTagsDeleteImmediately(bool $tags_delete_immediately): self
    {
        $this->tags_delete_immediately = $tags_delete_immediately;

        return $this;
    }

    public function getTagsShowPendingAdded(): bool
    {
        return $this->tags_show_pending_added;
    }

    public function setTagsShowPendingAdded(bool $tags_show_pending_added): self
    {
        $this->tags_show_pending_added = $tags_show_pending_added;

        return $this;
    }

    public function getTagsShowPendingDeleted(): bool
    {
        return $this->tags_show_pending_deleted;
    }

    public function setTagsShowPendingDeleted(bool $tags_show_pending_deleted): self
    {
        $this->tags_show_pending_deleted = $tags_show_pending_deleted;

        return $this;
    }
}
