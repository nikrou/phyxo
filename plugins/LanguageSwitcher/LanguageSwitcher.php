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

namespace Plugins\LanguageSwitcher;

use App\Events\BlockEvent;
use Phyxo\Block\DisplayBlock;
use Phyxo\Block\RegisteredBlock;
use Phyxo\Extension\AbstractPlugin;
use Symfony\Component\Intl\Locales;

class LanguageSwitcher extends AbstractPlugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            BlockEvent::class => 'addBlock'
        ];
    }

    public function addBlock(BlockEvent $event)
    {
        $menu = $event->getMenu();

        $menu->registerBlock(new RegisteredBlock('mbLanguages', 'Languages', 'languageSwitcher', [$this, 'languageData']));
    }

    public function languageData(DisplayBlock $block): void
    {
        $block->data['locales'] = [
            ['code' => 'fr_FR', 'name' => Locales::getName('fr', 'fr')],
            ['code' => 'en_GB', 'name' => Locales::getName('en', 'en')]
        ];
        $block->template = 'menubar_languages';

        $this->getThemeLoader()->addPath(__DIR__ . '/templates');
    }
}
