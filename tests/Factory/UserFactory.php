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

use App\Entity\User;
use Override;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @extends PersistentProxyObjectFactory<User&Proxy<User>>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     */
    public function __construct()
    {
    }

    public static function class(): string
    {
        return User::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'username' => self::faker()->userName(),
            'mail_address' => self::faker()->email(),
            'plain_password' => self::faker()->password(8),
        ];
    }

    public function withUserInfos(array|callable $attributes = []): self
    {
        return $this->with(static fn (): array => ['userInfos' => UserInfosFactory::new($attributes)]);
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[Override]
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(User $user): void {})
        ;
    }
}
