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

use App\Kernel;
use App\InstallKernel;
use App\UpdateKernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return function (array $context) {
    if (is_readable(dirname(__DIR__) . '/config/database.yaml')) {
        if (is_readable(dirname(__DIR__) . '/.update.mode')) {
            $kernel = new UpdateKernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
        } else {
            $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
        }
    } else {
        $kernel = new InstallKernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    }

    return $kernel;
};
