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

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

use mageekguy\atoum\asserter as Atoum;

/**
 * Features context.
 */
class FeatureContext extends MinkContext implements Context
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
     * @Given I visit :page page
     */
    public function iVisitPage($page) {
        $this->visit($this->pages[$page]);
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
        $this->iAmOnAProtectedPage();
        $this->getMink()->assertSession()->statusCodeEquals(401);
    }

    /**
     * @Then /^I should be allowed to go to a protected page$/
     */
    public function iShouldBeAllowedToGoToAProtectedPage() {
        $this->iAmOnAProtectedPage();
        $this->getMink()->assertSession()->statusCodeEquals(200);
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
        $album = $this->getAlbum($album_name);

        $this->getSession()->visit(sprintf($this->pages['album'], $album['id']));
        $this->getMink()->assertSession()->statusCodeEquals(401);
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

    /**
     * @Then /^I wait for message "([^"]*)" to appear$/
     */
    public function iWaitForMessageToAppear($message_type) {
        $this->getSession()->wait(
            2000,
            "$('a .infos').length > 0"
        );
    }

    /**
     * @Then I should see :count :status languages
     */
    public function iShouldSeeActiveLanguages($count, $status) {
        $container = $this->getSession()->getPage();
        $languages = $container->findAll('css', ".state.state-${status} .languages .language");

        if (intval($count) !== count($languages)) {
            $message = sprintf('%d %s languages found on the page, but should be %d.', count($languages), $status, $count);
            throw new ExpectationException($message, $this->session);
        }
    }

    /**
     * @Then I click on deactivate :language language
     */
    public function iClickOnDeactivateLanguage($language) {
        $container = $this->getSession()->getPage();
        $languages = $container->find('css', '.state.state-active .languages');
        $div_language = $languages->find('xpath', '//*[contains(text(), "'.$language.'")]');
        $link = $div_language->getParent()->find('named', ['link', $this->getSession()->getSelectorsHandler()->xpathLiteral('Deactivate')]);
        $link->click();
    }
}
