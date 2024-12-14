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

use App\DataMapper\UserMapper;
use App\Entity\Plugin;
use App\Repository\PluginRepository;
use App\Services\AssetsManager;
use App\Twig\ThemeLoader;
use Phyxo\Extension\AbstractPlugin;
use Phyxo\Extension\ExtensionCollection;
use Phyxo\Plugin\Plugins;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ExtensionManagerSubscriber implements EventSubscriberInterface
{
    private readonly Plugins $plugins;

    /**
     * @TODO: change Plugins interface to only accept language instead of UserMapper
     * event better : make Plugins a service
     */
    public function __construct(
        PluginRepository $pluginRepository,
        UserMapper $userMapper,
        private readonly AssetsManager $assetsManager,
        string $pluginsDir,
        string $pemURL,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ExtensionCollection $extensionCollection,
        private readonly ThemeLoader $themeLoader
    ) {
        $this->plugins = new Plugins($pluginRepository, $userMapper);
        $this->plugins->setRootPath($pluginsDir);
        $this->plugins->setExtensionsURL($pemURL);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['registerPlugins'],
            ConsoleEvents::COMMAND => ['checkAvailability']
        ];
    }

    public function registerPlugins(RequestEvent $event): void
    {
        if ($event->isMainRequest() === false) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get('_route') === 'admin_plugins_installed') {
            return;
        }

        foreach ($this->plugins->getDbPlugins(Plugin::ACTIVE) as $plugin) {
            $className = AbstractPlugin::getClassName($plugin->getId());

            if (class_exists($className) && method_exists($className, 'getSubscribedEvents')) {
                $this->eventDispatcher->addSubscriber(new $className($this->assetsManager, $this->themeLoader));
            }
        }
    }

    public function checkAvailability(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        $application = $command->getApplication();

        if ($command->getName() === 'list') {
            foreach ($this->plugins->getDbPlugins(Plugin::INACTIVE) as $plugin) {
                if ($this->extensionCollection->getExtensionsByClass()) {
                    foreach ($this->extensionCollection->getExtensionsByClass()[$plugin->getId()] as $command_name) {
                        $application->get($command_name)->setHidden(true);
                    }
                }
            }
        } elseif (isset($this->extensionCollection->getExtensionsByName()[$command->getName()])) {
            $command_name = $this->extensionCollection->getExtensionsByName()[$command->getName()];
            $pluginsForCommand = array_filter($this->plugins->getDbPlugins(Plugin::INACTIVE), fn ($p) => $p->getId() === $command_name);
            if ($pluginsForCommand !== []) {
                $event->disableCommand();
            }
        }
    }
}
