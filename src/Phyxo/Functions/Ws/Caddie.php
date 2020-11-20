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

namespace Phyxo\Functions\Ws;

use App\Entity\Caddie as EntityCaddie;
use Phyxo\Ws\Server;

class Caddie
{
    /**
     * API method
     * Adds images to the caddie
     * @param mixed[] $params
     *    @option int[] image_id
     */
    public static function add($params, Server $service)
    {
        foreach ($service->getImageMapper()->getRepository()->findBy(['id' => $params['image_id']]) as $image) {
            $caddie = new EntityCaddie();
            $caddie->setUser($service->getUserMapper()->getUser());
            $caddie->setImage($image);

            $service->getManagerRegistry()->getRepository(EntityCaddie::class)->addOrUpdateCaddie($caddie);
        }
    }
}
