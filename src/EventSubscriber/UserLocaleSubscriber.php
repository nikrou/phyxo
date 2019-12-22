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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class UserLocaleSubscriber implements EventSubscriberInterface
{
    private $session, $rememberMeCookie, $kernel;

    public function __construct(SessionInterface $session, string $rememberMeCookie, KernelInterface $kernel)
    {
        $this->session = $session;
        $this->rememberMeCookie = $rememberMeCookie;
        $this->kernel = $kernel;
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event, string $eventName, EventDispatcherInterface $dispatcher)
    {
        $user = $event->getAuthenticationToken()->getUser();

        if ($user->getLocale() !== null) {
            $this->session->set('_locale', $user->getLocale());
            $request = $event->getRequest();

            if ($request->cookies->has($this->rememberMeCookie) && !$this->session->has('_dispatch_remember_me')) {
                $this->session->set('_dispatch_remember_me', true);
                $request->setLocale($user->getLocale());

                $dispatcher->dispatch(KernelEvents::REQUEST, new RequestEvent($this->kernel, $request, HttpKernel::MASTER_REQUEST));
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin',
        ];
    }
}
