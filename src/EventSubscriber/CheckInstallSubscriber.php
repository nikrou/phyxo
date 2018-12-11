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
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use App\Exception\ConfigFileMissingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CheckInstallSubscriber implements EventSubscriberInterface
{
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return false;
        }

        $exception = $event->getException();
        $previous_exception = $exception->getPrevious();

        if (!($exception instanceof ConfigFileMissingException) && !($previous_exception instanceof ConfigFileMissingException)) {
            return false;
        }

        if ($event->getRequest()->get('_route') !== 'install') {
            $install_url = $this->urlGenerator->generate('install');
            $response = new RedirectResponse($install_url);
            $event->setResponse($response);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException'
        ];
    }
}
