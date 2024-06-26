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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class CheckInstallSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator, private readonly string $databaseYamlFile)
    {
    }

    public function onKernelException(ExceptionEvent $event)
    {
        if (!$event->isMainRequest()) {
            return false;
        }

        if (is_readable($this->databaseYamlFile)) {
            return false;
        }

        if ($event->getRequest()->get('_route') && ($event->getRequest()->get('_route') !== 'install')) {
            $install_url = $this->urlGenerator->generate('install');
            $response = new RedirectResponse($install_url);
            $event->setResponse($response);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException'
        ];
    }
}
