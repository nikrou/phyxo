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

use App\Utils\RuntimeTranslator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ExtensionTranslationSubscriber implements EventSubscriberInterface
{
    private $runtimeTranslator, $themesDir;

    public function __construct(RuntimeTranslator $runtimeTranslator, string $themesDir)
    {
        $this->runtimeTranslator = $runtimeTranslator;
        $this->themesDir = $themesDir;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['loadExtensionTranslation', 17],
        ];
    }

    public function loadExtensionTranslation(RequestEvent $event)
    {
        if ($event->isMainRequest() === false) {
            return;
        }

        $request = $event->getRequest();
        $locale = $request->getSession()->get('_locale');
        $theme = $request->getSession()->get('_theme');

        $translation_theme_file = sprintf('%s/%s/translations/messages+intl-icu.%s.php', $this->themesDir, $theme, $locale);
        if (is_readable($translation_theme_file)) {
            $this->runtimeTranslator->addRuntimeResource('php', $translation_theme_file, $locale, 'messages');
        }

        $translation_admin_file = sprintf('%s/%s/translations/admin+intl-icu.%s.php', $this->themesDir, $theme, $locale);
        if (is_readable($translation_admin_file)) {
            $this->runtimeTranslator->addRuntimeResource('php', $translation_admin_file, $locale, 'admin');
        }
    }
}
