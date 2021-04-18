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
use Phyxo\Extension\AbstractPlugin;
use Phyxo\Plugin\Plugins;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ExtensionManagerSubscriber implements EventSubscriberInterface
{
    private $plugins, $eventDispatcher, $assetsManager;

    /**
     * @TODO: change Plugins interface to only accept language instead of UserMapper
     * event better : make Plugins a service
     */
    public function __construct(PluginRepository $pluginRepository, UserMapper $userMapper, AssetsManager $assetsManager, string $pluginsDir, string $pemURL,
                                EventDispatcherInterface $eventDispatcher)
    {
        $this->plugins = new Plugins($pluginRepository, $userMapper);
        $this->plugins->setRootPath($pluginsDir);
        $this->plugins->setExtensionsURL($pemURL);

        $this->assetsManager = $assetsManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['registerPlugins'],
        ];
    }

    public function registerPlugins(RequestEvent $event)
    {
        if ($event->isMasterRequest() === false) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get('_route') === 'admin_plugins_installed') {
            return;
        }

        foreach ($this->plugins->getDbPlugins(Plugin::ACTIVE) as $plugin) {
            $className = AbstractPlugin::getClassName($plugin->getId());

            if (class_exists($className) && method_exists($className, 'getSubscribedEvents')) {
                $this->eventDispatcher->addSubscriber(new $className($this->assetsManager));
            }
        }
    }
}
