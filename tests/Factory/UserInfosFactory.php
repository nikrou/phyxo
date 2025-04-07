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

namespace App\Tests\Factory;

use App\Entity\UserInfos;
use App\Enum\UserStatusType;
use Override;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<UserInfos>
 */
final class UserInfosFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     */
    public function __construct()
    {
    }

    public static function class(): string
    {
        return UserInfos::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'enabled_high' => self::faker()->boolean(),
            'expand' => self::faker()->boolean(),
            'language' => self::faker()->text(50),
            'nb_image_page' => self::faker()->randomNumber(),
            'recent_period' => self::faker()->randomNumber(),
            'show_nb_comments' => self::faker()->boolean(),
            'show_nb_hits' => self::faker()->boolean(),
            'status' => self::faker()->randomElement(UserStatusType::cases()),
            'theme' => self::faker()->text(255),
        ];
    }

    public function guest(): self
    {
        return $this->with(['status' => UserStatusType::GUEST]);
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[Override]
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(UserInfos $userInfos): void {})
        ;
    }
}
