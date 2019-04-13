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

use App\Repository\ImageRepository;
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
        global $user, $conn;

        $result = (new ImageRepository($conn))->getImagesFromCaddie($params['image_id'], $user['id']);
        $ids = $conn->result2array($result, null, 'id');
        $datas = [];
        foreach ($ids as $id) {
            $datas[] = [
                'element_id' => $id,
                'user_id' => $user['id'],
            ];
        }
        if (count($datas)) {
            (new CaddieRepository($conn))->addElements(
                ['element_id', 'user_id'],
                $datas
            );
        }

        return count($datas);
    }
}
