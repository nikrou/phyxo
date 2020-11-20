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

use App\Repository\CaddieRepository;
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
        $result = (new CaddieRepository($service->getConnection()))->getImagesFromCaddie($params['image_id'], $service->getUserMapper()->getUser()->getId());
        $ids = $service->getConnection()->result2array($result, null, 'id');
        $datas = [];
        foreach ($ids as $id) {
            $datas[] = [
                'element_id' => $id,
                'user_id' => $service->getUserMapper()->getUser()->getId(),
            ];
        }
        if (count($datas)) {
            (new CaddieRepository($service->getConnection()))->addElements(
                ['element_id', 'user_id'],
                $datas
            );
        }

        return count($datas);
    }
}
