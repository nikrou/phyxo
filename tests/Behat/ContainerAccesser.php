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

namespace App\Tests\Behat;

trait ContainerAccesser
{
    protected function getConf()
    {
        return $this->getContainer()->get('phyxo.conf');
    }

    protected function getConnection()
    {
        return $this->getEntityManager()->getConnection();
    }

    protected function getEntityManager()
    {
        return $this->getContainer()->get('phyxo.entity.manager');
    }

    protected function getPasswordEncoder()
    {
        return $this->getContainer()->get('security.password_encoder');
    }

    protected function getUserManager()
    {
        return $this->getContainer()->get('phyxo.user.manager');
    }

    protected function getUserMapper()
    {
        return $this->getContainer()->get('phyxo.user.mapper');
    }

    protected function getCategoryMapper()
    {
        return $this->getContainer()->get('phyxo.category.mapper');
    }

    protected function getImageMapper()
    {
        return $this->getContainer()->get('phyxo.image.mapper');
    }

    protected function getTagMapper()
    {
        return $this->getContainer()->get('phyxo.tag.mapper');
    }

    protected function getCommentMapper()
    {
        return $this->getContainer()->get('phyxo.comment.mapper');
    }
}
