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

namespace App\Utils;

use Phyxo\DBLayer\DBLayer;
use Phyxo\Conf;
use App\Repository\UserRepository;
use App\Repository\GroupRepository;
use App\Repository\UserGroupRepository;
use App\Entity\User;
use App\Repository\UserInfosRepository;

class UserManager
{
    private $conn, $conf, $passwordEncoder;

    public function __construct(DBLayer $conn, Conf $conf)
    {
        $this->conn = $conn;
        $this->conf = $conf;
    }

    public function register(User $user)
    {
        $insert = [
            'username' => $user->getUsername(),
            'password' => $user->getPassword(),
            'mail_address' => $user->getMailAddress()
        ];

        $user_id = (new UserRepository($this->conn))->addUser($insert);

        // Assign by default groups
        $result = (new GroupRepository($this->conn))->findByField('is_default', true, 'ORDER BY id ASC');
        $inserts = [];
        while ($row = $this->conn->db_fetch_assoc($result)) {
            $inserts[] = [
                'user_id' => $user_id,
                'group_id' => $row['id']
            ];
        }

        if (count($inserts) != 0) {
            (new UserGroupRepository($this->conn))->massInserts(['user_id', 'group_id'], $inserts);
        }

        $this->createUserInfos([$user_id]);
    }

    /**
     * Creates user informations based on default values.
     */
    public function createUserInfos(array $user_ids, array $override_values = [])
    {
        if (!empty($user_ids)) {
            $inserts = [];

            $default_user = $this->getDefaultUserInfo(false);
            if ($default_user === false) {
                // Default on structure are used
                $default_user = [];
            }

            if (!empty($override_values)) {
                $default_user = array_merge($default_user, $override_values);
            }

            foreach ($user_ids as $user_id) {
                $level = isset($default_user['level']) ? $default_user['level'] : 0;
                if ($user_id == $this->conf['webmaster_id']) {
                    $status = 'webmaster';
                    $level = max($this->conf['available_permission_levels']);
                } elseif (($user_id == $this->conf['guest_id']) or ($user_id == $this->conf['default_user_id'])) {
                    $status = 'guest';
                } else {
                    $status = 'normal';
                }

                $insert = array_merge(
                    $default_user,
                    [
                        'user_id' => $user_id,
                        'status' => $status,
                        'registration_date' => 'now()',
                        'level' => $level
                    ]
                );

                $inserts[] = $insert;
            }

            (new UserInfosRepository($this->conn))->massInserts(array_keys($inserts[0]), $inserts);
        }
    }

    public function getDefaultUserInfo() : array
    {
        $result = (new UserInfosRepository($this->conn))->findByUserId($this->conf['default_user_id']);
        $default_user = $this->conn->db_fetch_assoc($result);

        foreach ($default_user as &$value) {
                // If the field is true or false, the variable is transformed into a boolean value.
            if (!is_null($value) && $this->conn->is_boolean($value)) {
                $value = $this->conn->get_boolean($value);
            }
        }

        return $default_user;
    }
}
