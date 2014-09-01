<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
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

use Behat\MinkExtension\Context\MinkContext;
use Behat\Behat\Exception\PendingException;
use Behat\Behat\Context\Step\Then;
use Behat\Behat\Context\Step\When;
use Behat\Gherkin\Node\PyStringNode;

use mageekguy\atoum\asserter as Atoum;

/**
 * Features context.
 */
class FeatureContext extends MinkContext
{
    /**
     * Initializes context.
     * Every scenario gets its own context object.
     *
     * @param array $parameters context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters) {
        $this->assert = new Atoum\generator();

        $this->parameters = $parameters;
        $this->pages = $parameters['pages'];

        $this->useContext('db', new DbContext($parameters));
        $this->useContext('api', new GuzzleApiContext($parameters));
    }

    /**
     * @Given /^I am logged in as "([^"]*)" with password "([^"]*)"$/
     */
    public function iAmLoggedInAsWithPassword($username, $password, $remember=false) {
        $this->visit($this->pages['identification']);
        $this->fillField('username', $username);
        $this->fillField('password', $password);
        if ($remember) {
            $this->checkOption('remember_me');
        }
        $this->pressButton('login');
    }

    /**
     * @Given /^I am logged in as "([^"]*)" with password "([^"]*)" and auto login$/
     */
    public function iAmLoggedInAsWithPasswordAndAutoLogin($username, $password) {
        $this->iAmLoggedInAsWithPassword($username, $password, true);
        $this->getMink()->assertSession()->cookieExists('phyxo_id');
        $this->getMink()->assertSession()->cookieExists('phyxo_remember');
    }

    /**
     * @Given /^I am on a protected page$/
     * @When /^(?:|I )go to a protected page$/
     */
    public function iAmOnAProtectedPage() {
        $this->visit($this->pages['protected_page']);
    }

    /**
     * @Then /^I should not be allowed to go to a protected page$/
     */
    public function iShouldNotBeAllowedToGoToAProtectedPage() {
        return array(
            $this->iAmOnAProtectedPage(),
            new Then('the response status code should be 401'),
        );
    }

    /**
     * @Then /^I should be allowed to go to a protected page$/
     */
    public function iShouldBeAllowedToGoToAProtectedPage() {
        return array(
            $this->iAmOnAProtectedPage(),
            new Then('the response status code should be 200'),
        );
    }

    /**
     * @Then /^I should be logged in$/
     */
    public function iShouldBeLoggedIn() {
        $this->getMink()->assertSession()->cookieExists('phyxo_id');
    }

    /**
     * @When /^I restart my browser$/
     */
    public function iRestartMyBrowser() {
        $session = $this->getSession();
        $cookie = $session->getCookie('phyxo_remember'); // @TODO: retrieve cookie name from conf
        $session->restart();
        $session->setCookie('phyxo_remember', $cookie);
    }

    /**
     * @Given /^I should not be allowed to go to album "([^"]*)"$/
     */
    public function iShouldNotBeAllowedToGoToAlbum($album_name) {
        $album = $this->getSubcontext('db')->getAlbum($album_name);

        return array(
            new When(sprintf('I go to "'.$this->pages['album'].'"', $album->id)),
            new Then('the response status code should be 401'),
        );
    }

    /**
     * @When /^I add a comment :$/
     */
    public function whenIAddAComment(PyStringNode $comment) {
        $this->fillField('Comment', $comment);
        $this->pressButton('Submit');
    }

    /**
     * @When /^I clik rate button (\d+)$/
     */
    public function whenIClikRateButton($note) {
        $formRate = $this->getSession()->getPage()->find('css', '#rateForm');
        if ($formRate!==null) {
            $star = $formRate->find('css', 'input[title=\''.$note.'\']');
            $star->click();
        }
    }
}
