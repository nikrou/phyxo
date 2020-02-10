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

namespace App\Tests\Behat;

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Mink\Exception\ExpectationException;

abstract class BaseContext extends RawMinkContext implements Context
{
    public function __call($method, $parameters)
    {
        $page = $this->getSession()->getPage();
        if (method_exists($page, $method)) {
            return call_user_func_array([$page, $method], $parameters);
        }

        $session = $this->getSession();
        if (method_exists($session, $method)) {
            return call_user_func_array([$session, $method], $parameters);
        }

        throw new \RuntimeException(sprintf('The "%s()" method does not exist.', $method));
    }

    /**
     * example:
     *     $this->spins(function() use ($text) {
     *         $this->assertSession()->pageTextContains($text);
     *     });
     */
    public function spins($closure, $tries = 3)
    {
        for ($i = 0; $i <= $tries; $i++) {
            try {
                $closure();

                return;
            } catch (\Exception $e) {
                if ($i == $tries) {
                    throw $e;
                }
            }

            sleep(1);
        }
    }

    protected function throwExpectationException($message)
    {
        throw new ExpectationException($message, $this->getSession());
    }
}
