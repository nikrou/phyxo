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

namespace App;

use Override;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class InstallKernel extends Kernel
{
    use IdentityGeneratorTrait;

    #[Override]
    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment . '/install/';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/{packages}/*.yaml');
        $container->import('../config/{packages}/' . $this->environment . '/*.yaml');

        if (is_file(\dirname(__DIR__) . '/config/services_install.yaml')) {
            $container->import('../config/services_install.yaml');
            if (is_file(\dirname(__DIR__) . '/config/services_install_' . $this->environment . '.yaml')) {
                $container->import('../config/services_install_' . $this->environment . '.yaml');
            }
        }
    }
}
