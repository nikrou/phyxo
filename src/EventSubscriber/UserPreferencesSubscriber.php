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

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class UserPreferencesSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly string $rememberMeCookie, private readonly KernelInterface $kernel)
    {
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $request = $event->getRequest();

        /** @var User $user */
        $user = $event->getAuthenticationToken()->getUser();

        if ($user->getLocale() !== null) {
            $request->getSession()->set('_locale', $user->getLocale());
        }

        if ($user->getTheme() !== null) {
            $request->getSession()->set('_theme', $user->getTheme());
        }

        if ($request->cookies->has($this->rememberMeCookie) && !$request->getSession()->has('_dispatch_remember_me')) {
            $request->getSession()->set('_dispatch_remember_me', true);

            $dispatcher->dispatch(new RequestEvent($this->kernel, $request, HttpKernel::MAIN_REQUEST));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin',
        ];
    }
}
