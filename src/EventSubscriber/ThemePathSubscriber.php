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

use App\Twig\ThemeLoader;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ThemePathSubscriber implements EventSubscriberInterface
{
    private $themeLoader, $themesDir, $defaultTheme;

    public function __construct(ThemeLoader $themeLoader, string $themesDir, string $defaultTheme)
    {
        $this->themeLoader = $themeLoader;
        $this->themesDir = $themesDir;
        $this->defaultTheme = $defaultTheme;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['addThemePath', 18],
        ];
    }

    public function addThemePath(RequestEvent $event)
    {
        if ($event->isMainRequest() === false) {
            return;
        }

        $request = $event->getRequest();

        if (preg_match('`^/admin/`', $request->getPathInfo())) { // Do not add public themes on admin URLs
            return;
        }

        $theme = $request->getSession()->get('_theme', $this->defaultTheme);
        if (is_dir($this->themesDir . '/' . $theme . '/template')) {
            $request->attributes->set('_theme', $theme);
            $this->themeLoader->addPath($this->themesDir . '/' . $theme . '/template');
        }
    }
}
