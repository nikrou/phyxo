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

use Phyxo\DBLayer\iDBLayer;
use Phyxo\Conf;
use App\Repository\UserRepository;
use App\Repository\GroupRepository;
use App\Repository\UserGroupRepository;
use App\Entity\User;
use App\Repository\UserInfosRepository;

class UserManager
{
    private $conn, $conf;

    public function __construct(iDBLayer $conn, Conf $conf)
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

        if ($user->getId()) {
            $user_id = $insert['id'] = $user->getId();
            (new UserRepository($this->conn))->addUser($insert, $auto_increment_for_table = false);
        } else {
            $user_id = (new UserRepository($this->conn))->addUser($insert, $auto_increment_for_table = true);
        }

        // Assign by default groups
        $result = (new GroupRepository($this->conn))->findByField('is_default', true, 'ORDER BY id ASC');
        $inserts = [];
        while ($row = $this->conn->db_fetch_assoc($result)) {
            $inserts[] = [
                'user_id' => $user_id,
                'group_id' => $row['id']
            ];
        }

        if (count($inserts) !== 0) {
            (new UserGroupRepository($this->conn))->massInserts(['user_id', 'group_id'], $inserts);
        }

        $this->createUserInfos([$user_id]);

        return $user_id;
    }

    /**
     * Creates user informations based on default values.
     */
    protected function createUserInfos(array $user_ids, array $override_values = [])
    {
        if (!empty($user_ids)) {
            $inserts = [];

            $default_user = $this->getDefaultUserInfo();

            if (!empty($override_values)) {
                $default_user = array_merge($default_user, $override_values);
            }

            $now = (new \DateTime())->format('Y-m-d H:i:s');
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
                        'registration_date' => $now,
                        'level' => $level
                    ]
                );

                $inserts[] = $insert;
            }

            (new UserInfosRepository($this->conn))->massInserts(array_keys($inserts[0]), $inserts);
        }
    }

    public function getDefaultUserInfo(): array
    {
        $result = (new UserInfosRepository($this->conn))->findByUserId($this->conf['default_user_id']);
        if ($default_user = $this->conn->db_fetch_assoc($result)) {
            foreach ($default_user as &$value) {
                // If the field is true or false, the variable is transformed into a boolean value.
                if (!is_null($value) && $this->conn->is_boolean($value)) {
                    $value = $this->conn->get_boolean($value);
                }
            }

            return $default_user;
        } else {
            return [];
        }
    }

    public function findUserByUsernameOrEmail(string $username_or_email)
    {
        $result = (new UserRepository($this->conn))->findByEmail($username_or_email);
        if ($this->conn->db_num_rows($result) > 0) {
            return $this->conn->db_fetch_assoc($result);
        }

        $result = (new UserRepository($this->conn))->findByUsername($username_or_email);
        if ($this->conn->db_num_rows($result) == 0) {
            return false;
        } else {
            return $this->conn->db_fetch_assoc($result);
        }
    }

    public function generateActivationKey(int $size = 50) {
        return bin2hex(random_bytes($size));
    }
}
