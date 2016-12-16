<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Mink\Exception\ExpectationException;

abstract class BaseContext extends MinkContext implements Context
{
    protected static $conf_loaded = false;

    /**
     * example:
     *     $this->spins(function() use ($text) {
     *         $this->assertSession()->pageTextContains($text);
     *     });
     */
    public function spins($closure, $tries=3) {
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

    protected function throwExpectationException($message) {
        throw new ExpectationException($message, $this->getSession());
    }
}