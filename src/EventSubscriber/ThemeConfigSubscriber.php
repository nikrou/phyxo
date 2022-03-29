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
use Phyxo\Theme\Themes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class ThemeConfigSubscriber implements EventSubscriberInterface
{
    private Environment $twig;
    private Conf $conf;
    private Themes $themes;

    public function __construct(Themes $themes, Conf $conf, Environment $twig)
    {
        $this->themes = $themes;
        $this->conf = $conf;
        $this->twig = $twig;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if ($event->isMainRequest() === false) {
            return;
        }

        foreach ($this->themes->getDbThemes() as $theme) {
            $className = AbstractTheme::getClassName($theme->getId());

            if (class_exists($className)) {
                $theme_instance = new $className($this->conf);

                $this->twig->addGlobal('theme_config', $theme_instance->getConfig());
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest'
        ];
    }
}
