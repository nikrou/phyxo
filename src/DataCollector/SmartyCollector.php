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

namespace App\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Phyxo\Template\Template;

/**
 * SmartyCollector.
 *
 */
class SmartyCollector extends DataCollector
{
    private $template;

    public function __construct(Template $template)
    {
        $this->template = $template;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $stats = $this->template->getStats();

        $this->data = [
            'time' => $stats['render_time'] * 1000,
            'templates' => $stats['files'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->data = [];
    }

    public function getTime()
    {
        return $this->data['time'];
    }

    public function getTemplates()
    {
        return $this->data['templates'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'app.smarty_collector';
    }
}
