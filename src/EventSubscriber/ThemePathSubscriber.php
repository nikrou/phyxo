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
use Phyxo\Theme\Themes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ThemePathSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ThemeLoader $themeLoader,
        private readonly string $themesDir,
        private readonly string $defaultTheme
    ) {
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

            $theme_config_file = sprintf('%s/%s/%s', $this->themesDir, $theme, Themes::CONFIG_FILE);
            $themeParameters = Themes::loadThemeParameters($theme_config_file, $theme, $this->themesDir);

            if (!empty($themeParameters['parent']) && is_dir($this->themesDir . '/' . $themeParameters['parent'] . '/template')) {
                $this->themeLoader->addPath($this->themesDir . '/' . $themeParameters['parent'] . '/template');
            }
        }
    }
}
