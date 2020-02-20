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

use Behat\Symfony2Extension\Context\KernelDictionary;
use mageekguy\atoum\asserter as Atoum;

class FeatureContext extends BaseContext
{
    use KernelDictionary;
    use ContainerAccesser;

    private $storage;

    public function __construct(Storage $storage)
    {
        $this->assert = new Atoum\generator();
        $this->storage = $storage;
    }

    /**
     * @Given I am logged in as :username with password :password
     */
    public function iAmLoggedInAsWithPassword(string $username, string $password, bool $remember = false)
    {
        $this->visit('/identification');
        $this->fillField('Username', $username);
        $this->fillField('Password', $password);
        if ($remember) {
            $this->checkField('Auto login');
        }
        $this->pressButton('Submit');
    }

    /**
     * @Given I am logged in as :username with password :password and auto login
     */
    public function iAmLoggedInAsWithPasswordAndAutoLogin($username, $password)
    {
        $this->iAmLoggedInAsWithPassword($username, $password, true);
        $this->getMink()->assertSession()->cookieExists($this->getCookieName());
    }

    /**
     * @Then I should be allowed to go to a protected page
     */
    public function iShouldBeAllowedToGoToAProtectedPage()
    {
        $this->visit($this->getContainer()->get('router')->generate('profile'));
    }

    /**
     * @Then /^I should not be allowed to go to a protected page$/
     */
    public function iShouldNotBeAllowedToGoToAProtectedPage()
    {
        $this->visit($this->getContainer()->get('router')->generate('profile'));
        $this->getMink()->assertSession()->statusCodeEquals(403);
    }

    /**
     * @Then I should not be allowed to go to album :album_name
     */
    public function iShouldNotBeAllowedToGoToAlbum(string $album_name)
    {
        $this->visit($this->getContainer()->get('router')->generate('album', ['category_id' => $this->storage->get('album_' . $album_name)]));
        $this->getMink()->assertSession()->statusCodeEquals(403);
        $this->getMink()->assertSession()->pageTextContains('The server returned a "403 Forbidden".');
    }

    /**
     * @When /^I restart my browser$/
     */
    public function iRestartMyBrowser()
    {
        $session = $this->getSession();
        $cookie = $session->getCookie($this->getCookieName());
        $session->restart();
        $session->setCookie($this->getCookieName(), $cookie);
    }

    private function getCookieName()
    {
        return $this->getContainer()->getParameter('remember_cookie');
    }
}
