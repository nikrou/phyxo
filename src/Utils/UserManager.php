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

use Phyxo\Conf;
use App\Repository\UserRepository;
use App\Repository\GroupRepository;
use App\Repository\UserGroupRepository;
use App\Entity\User;
use App\Repository\UserInfosRepository;
use Phyxo\EntityManager;

class UserManager
{
    private $em, $conf;

    public function __construct(EntityManager $em, Conf $conf)
    {
        $this->em = $em;
        $this->conf = $conf;
    }

    public function register(User $user): int
    {
        $insert = [
            'username' => $user->getUsername(),
            'password' => $user->getPassword(),
            'mail_address' => $user->getMailAddress()
        ];

        if ($user->getId()) {
            $user_id = $user->getId();
            $this->em->getRepository(UserRepository::class)->addUser($insert, $auto_increment_for_table = false);
        } else {
            $user_id = $this->em->getRepository(UserRepository::class)->addUser($insert, $auto_increment_for_table = true);
        }

        // Assign by default groups
        $result = $this->em->getRepository(GroupRepository::class)->findByField('is_default', true, 'ORDER BY id ASC');
        $inserts = [];
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            $inserts[] = [
                'user_id' => $user_id,
                'group_id' => $row['id']
            ];
        }

        if (count($inserts) !== 0) {
            $this->em->getRepository(UserGroupRepository::class)->massInserts(['user_id', 'group_id'], $inserts);
        }

        $this->createUserInfos($user_id, $user->getStatus() ?? User::STATUS_NORMAL);

        return $user_id;
    }

    /**
     * Creates user informations based on default values.
     */
    protected function createUserInfos(int $user_id, string $status = User::STATUS_NORMAL, array $override_values = [])
    {
        $default_user = $this->getDefaultUserInfo();

        if (!empty($override_values)) {
            $default_user = array_merge($default_user, $override_values);
        }

        $now = (new \DateTime())->format('Y-m-d H:i:s');
        $level = isset($default_user['level']) ? $default_user['level'] : 0;

        // @TODO: only one webmaster and only one guest
        if ($status === User::STATUS_WEBMASTER) {
            $level = max($this->conf['available_permission_levels']);
        }

        $inserts[] = array_merge(
            $default_user,
            [
                'user_id' => $user_id,
                'status' => $status,
                'registration_date' => $now,
                'level' => $level
            ]
        );

        $this->em->getRepository(UserInfosRepository::class)->massInserts(array_keys($inserts[0]), $inserts);
    }

    public function getDefaultUserInfo(): array
    {
        $result = $this->em->getRepository(UserInfosRepository::class)->findByUserId($this->conf['default_user_id']);
        if ($default_user = $this->em->getConnection()->db_fetch_assoc($result)) {
            foreach ($default_user as &$value) {
                // If the field is true or false, the variable is transformed into a boolean value.
                if (!is_null($value) && $this->em->getConnection()->is_boolean($value)) {
                    $value = $this->em->getConnection()->get_boolean($value);
                }
            }

            return $default_user;
        } else {
            return [];
        }
    }

    public function findUserByUsernameOrEmail(string $username_or_email)
    {
        $result = $this->em->getRepository(UserRepository::class)->findByEmail($username_or_email);
        if ($this->em->getConnection()->db_num_rows($result) > 0) {
            return $this->em->getConnection()->db_fetch_assoc($result);
        }

        $result = $this->em->getRepository(UserRepository::class)->findByUsername($username_or_email);
        if ($this->em->getConnection()->db_num_rows($result) == 0) {
            return false;
        } else {
            return $this->em->getConnection()->db_fetch_assoc($result);
        }
    }

    public function generateActivationKey(int $size = 50)
    {
        return bin2hex(random_bytes($size));
    }
}
