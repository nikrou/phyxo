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
use Behat\Mink\Element\NodeElement;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Mink\Exception\ExpectationException;
use Closure;

/**
 * @method getPage()
 * @method findLink(string $locator)
 * @method visit(string $url)
 * @method fillField(string $locator, string $value)
 * @method findField(string $locator)
 * @method checkField(string $locator)
 * @method pressButton(string $locator)
 */
abstract class BaseContext extends RawMinkContext implements Context
{
    /**
     * @param mixed $parameters
     *
     * @return mixed
     */
    public function __call(string $method, $parameters)
    {
        $page = $this->getSession()->getPage();
        if (method_exists($page, $method)) {
            return call_user_func_array([$page, $method], $parameters);
        }

        $session = $this->getSession();
        if (method_exists($session, $method)) {
            return call_user_func_array([$session, $method], $parameters);
        }

        throw new \RuntimeException(sprintf('The "%s()" method does not exist in DocumentElement(page) nor Mink(session).', $method));
    }

    /**
     * example:
     *     $this->spins(function() use ($text) {
     *         $this->assertSession()->pageTextContains($text);
     *     });
     */
    public function spins(Closure $closure, int $tries = 3): void
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

    public function findByDataTestid(string $data_id, NodeElement $parent = null): NodeElement
    {
        if ($parent === null) {
            $parent = $this->getSession()->getPage();
        }
        $element = $parent->find('css', sprintf('*[data-testid="%s"]', $data_id));

        if ($element === null) {
            throw new \Exception(sprintf('Element with data-testid="%s" not found', $data_id));
        }

        return $element;
    }

    protected function throwExpectationException(string $message): void
    {
        throw new ExpectationException($message, $this->getSession());
    }
}
