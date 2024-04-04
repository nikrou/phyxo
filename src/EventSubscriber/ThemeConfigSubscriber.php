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

use Phyxo\Conf;
use Phyxo\Extension\AbstractTheme;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class ThemeConfigSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Conf $conf,
        private readonly Environment $twig,
        private readonly string $defaultTheme,
    ) {
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if ($event->isMainRequest() === false) {
            return;
        }

        $request = $event->getRequest();

        $theme = $request->getSession()->get('_theme', $this->defaultTheme);
        $className = AbstractTheme::getClassName($theme);
        if (class_exists($className)) {
            $theme_instance = new $className($this->conf);
            $this->twig->addGlobal('theme_config', $theme_instance->getConfig());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest'
        ];
    }
}
