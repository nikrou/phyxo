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

use App\Entity\Rate as EntityRate;
use Phyxo\Ws\Server;

class Rate
{
    /**
     * API method
     * Deletes rates of an user.
     *
     * @param mixed[] $params
     *
     *    @option int user_id
     *    @option string anonymous_id (optional)
     */
    public function delete($params, Server $service)
    {
        $changes = $service->getManagerRegistry()->getRepository(EntityRate::class)->deleteWithConditions(
            $params['user_id'],
            empty($params['anonymous_id']) ? null : $params['anonymous_id'],
            empty($params['image_id']) ? null : $params['image_id']
        );

        if ($changes) {
            $service->getRateMapper()->UpdateRatingScore();
        }

        return $changes;
    }
}
