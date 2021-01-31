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

use Behat\Gherkin\Node\PyStringNode;
use Behat\Symfony2Extension\Context\KernelDictionary;
use mageekguy\atoum\asserter as Atoum;

class FeatureContext extends BaseContext
{
    use KernelDictionary;

    private $assert, $storage;

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
     * @Then I should not be allowed to go to a protected page
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
        $this->visit($this->getContainer()->get('router')->generate('album', ['category_id' => $this->storage->get('album_' . $album_name)->getId()]));
        $this->getMink()->assertSession()->statusCodeEquals(403);
        $this->getMink()->assertSession()->pageTextContains('The server returned a "403 Forbidden".');
    }

    protected function isPhotoInPage(int $image_id)
    {
        return $this->getPage()->find('css', '[data-photo-id="' . $image_id . '"]');
    }

    /**
     * @Then I should see photo :photo_name
     */
    public function iShouldSeePhoto(string $photo_name)
    {
        $image_id = $this->storage->get('image_' . $photo_name)->getId();
        if (!$this->isPhotoInPage($image_id)) {
            throw new \Exception(sprintf('Photo "%s" not found in the page', $photo_name));
        }
    }

    /**
     * @Then I should not see photo :photo_name
     */
    public function iShouldNotSeePhoto(string $photo_name)
    {
        $image_id = $this->storage->get('image_' . $photo_name)->getId();
        if ($this->isPhotoInPage($image_id)) {
            throw new \Exception(sprintf('Photo "%s" was found in the page but should not', $photo_name));
        }
    }

    protected function beAbleToEditTags()
    {
        return $this->getPage()->find('css', '.edit-tags');
    }

    /**
     * @Then I should not be able to edit tags
     */
    public function iShouldNotBeAbleToEditTags()
    {
        if ($this->beAbleToEditTags()) {
            throw new \Exception('User can edit tags but should not');
        }
    }

    /**
     * @Then I should be able to edit tags
     */
    public function iShouldBeAbleToEditTags()
    {
        if (!$this->beAbleToEditTags()) {
            throw new \Exception('User cannot edit tags but should be able to');
        }
    }

    /**
     * @Then I should see tag :tag
     */
    public function iShouldSeeTag(string $tag_name)
    {
        $tags = $this->getPage()->find('css', '#Tags');
        if ($tags === null) {
            throw new \Exception('No tags found on the page');
        }

        $tag_link = $tags->find('xpath', '//*[contains(text(), "' . $tag_name . '")]');
        if ($tag_link === null) {
            throw new \Exception(sprintf('Tag "%s" not found on the page but should be', $tag_name));
        }
    }

    /**
     * @Then I should not see tag :tag
     */
    public function iShouldNotSeeTag(string $tag_name)
    {
        $tags = $this->getPage()->find('css', '#Tags');
        if ($tags !== null) {
            $tag_link = $tags->find('xpath', '//*[contains(text(), "' . $tag_name . '")]');
            if ($tag_link !== null) {
                throw new \Exception(sprintf('Tag "%s" found on the page but should not be', $tag_name));
            }
        }
    }

    /**
     * @When I should see link :link_label
     * @Then I should see link :arg1 in :parent
     */
    public function iShouldSeeLink(string $link_label, string $parent = '')
    {
        if (!empty($parent)) {
            $parentNode = $this->getSession()->getPage()->find('css', $parent);
        } else {
            $parentNode = $this->getSession()->getPage();
        }
        $link = $parentNode->findLink($link_label);

        if ($link === null) {
            throw new \Exception(sprintf('Link "%s" not found on the page but should be', $link_label));
        }
    }

    /**
     * @When I should not see link :link_label
     */
    public function iShouldNotSeeLink(string $link_label)
    {
        $link = $this->findLink($link_label);

        if ($link !== null) {
            throw new \Exception(sprintf('Link "%s" found on the page but should not be', $link_label));
        }
    }

    /**
     * @Then I should see :description for :album_name description
     */
    public function iShouldSeeForDescription(string $description, string $album_name)
    {
        $album = $this->getPage()->find('css', sprintf('*[data-id="%d"]', $this->storage->get('album_' . $album_name)->getId()));
        $this->assert
            ->string($description)
            ->isEqualTo($this->findByDataTestid('album-description', $album)->getText());
    }

    /**
     * @Then I should see :nb_images for :album_name number of images
     */
    public function iShouldSeeForNumberOfImages(string $nb_images, string $album_name)
    {
        $album = $this->getPage()->find('css', sprintf('*[data-id="%d"]', $this->storage->get('album_' . $album_name)->getId()));
        $element = $this->findByDataTestid('album-nb-images', $album);

        // @FIX:  Element visibility check is not supported by Behat\Symfony2Extension\Driver\KernelDriver
        // if (!$element->isVisible()) {
        //     throw new \Exception('Number of images exists but it is not visible');
        // }

        $this->assert
            ->string($nb_images)
            ->isEqualTo($element->getText());
    }

    /**
     * @When I add a comment :
     */
    public function iAddAComment(PyStringNode $comment)
    {
        $this->fillField('Comment', $comment);
        $this->pressButton('Submit');
    }

    /**
     * @Then the option :option from :from" is selected
     */
    public function theOptionFromSelectIsSelected(string $option, string $from)
    {
        $selectField = $this->findField($from);

        if ($selectField === null) {
            throw new \Exception(sprintf('The select "%s" was not found in the page', $from));
        }

        $optionField = $selectField->find('xpath', "//option[@selected]");
        if ($optionField === null) {
            throw new \Exception(sprintf('No option is selected in the %s select', $from));
        }

        if ($optionField->getValue() !== $option) {
            throw new \Exception(sprintf('The option "%s" was not selected but should be', $option));
        }
    }

    /**
     * @Then the radio button :value from :name should be selected
     */
    public function theRadioButtonFromShouldBeSelected(string $value, string $name)
    {
        $radioButton = $this->getSession()->getPage()->find('css', sprintf('input[type="radio"][name="%s"][value="%s"]', $name, $value));

        if (is_null($radioButton)) {
            throw new \Exception(sprintf('Button radio "%s" with value "%s" not found but should be', $name, $value));
        }

        if (!$radioButton->isChecked()) {
            throw new \Exception(sprintf('Button radio "%s" with value "%s" should be checked but is not', $name, $value));
        }
    }

    /**
     * @Then I should not see link to :href
     */
    public function iShouldNotSeeLinkTo(string $href)
    {
        $link = $this->findLink($href);
        if (!is_null($link)) {
            throw new \Exception(sprintf('Link with href "%s" was found but shoud not', $link->getAttribute('href')));
        }
    }

    /**
     * @Then the select :element should contain:
     */
    public function theSelectShouldContain(string $element, PyStringNode $expectedString)
    {
        $select = $this->findField($element);
        if (is_null($select)) {
            throw new \Exception(sprintf('Select "%s" not found but should be', $element));
        }

        $options = [];
        $fields = $select->findAll('css', 'option');
        if (count($fields) > 0) {
            foreach ($fields as $field) {
                $options[] = $field->getText();
            }
        }

        $expectedOptions = $expectedString->getStrings();
        sort($options);
        sort($expectedOptions);

        if (array_diff($options, $expectedOptions) !== array_diff($expectedOptions, $options)) {
            throw new \Exception(sprintf('Element "%s" should contain "%s" but contains "%s".', $element, implode('|', $expectedOptions), implode('|', $options)));
        }
    }

    /**
     * @Then the group :group should have members :users
     */
    public function theGroupShouldHaveMembers(string $group, string $users)
    {
        $rowGroup = $this->getPage()->find('css', sprintf('table tr:contains("%s")', $group));
        if (is_null($rowGroup)) {
            throw new \Exception(sprintf('Cannot find a group "%s" on the page', $group));
        }
        $members = $rowGroup->find('css', 'td[class="members"]');
        if (is_null($members)) {
            throw new \Exception(sprintf('Cannot find members cell for group "%s" on the page', $group));
        }
        $foundMembers = implode(',', explode(' ', $members->getText()));
        if ($foundMembers !== $users) {
            throw new \Exception(sprintf('Members for group "%s" are "%s" but should be "%s".', $group, $foundMembers, $users));
        }
    }

    /**
     * @Given I follow group :group permissions
     */
    public function iFollowGroupPermissions(string $group)
    {
        $rowGroup = $this->getPage()->find('css', sprintf('table tr:contains("%s")', $group));
        if (is_null($rowGroup)) {
            throw new \Exception(sprintf('Cannot find a group "%s" on the page', $group));
        }
        $rowGroup->clickLink('Permissions');
    }

    /**
     * @Given I follow album :album_name Edit
     */
    public function iFollowAlbumEdit(string $album_name)
    {
        $album = $this->storage->get('album_' . $album_name);
        $divAlbum = $this->getPage()->find('css', sprintf('#album-%d', $album->getId()));
        if (is_null($divAlbum)) {
            throw new \Exception(sprintf('Cannot find an album "%s" on the page', $album_name));
        }
        $divAlbum->clickLink('Edit');
    }

    /**
     * @Given I want to edit :image_name
     */
    public function iWantToEdit(string $image_name)
    {
        $image = $this->storage->get('image_' . $image_name);
        $this->visit($this->getContainer()->get('router')->generate('admin_photo', ['image_id' => $image->getId()]));
    }

    /**
     * @Then linked albums :element should be album :album_name
     */
    public function linkedAlbumsShouldBeAlbum(string $element, string $album_name)
    {
        $select = $this->findField($element);
        if (is_null($select)) {
            throw new \Exception(sprintf('Select "%s" not found but should be', $element));
        }

        $options = [];
        $albums_value = $select->getAttribute('data-value');
        if (!is_null($albums_value)) {
            $options = json_decode($albums_value, true);
        }

        $expectedOptions = [$this->storage->get('album_' . $album_name)->getId()];
        sort($options);
        sort($expectedOptions);

        if (array_diff($options, $expectedOptions) !== array_diff($expectedOptions, $options)) {
            throw new \Exception(sprintf('Element "%s" should contain "%s" but contains "%s".', $element, implode('|', $expectedOptions), implode('|', $options)));
        }
    }

    /**
     * @Then tags :element should be :list_tags
     */
    public function tagsShouldBeAlbum(string $element, string $list_tags)
    {
        $select = $this->findField($element);
        if (is_null($select)) {
            throw new \Exception(sprintf('Select "%s" not found but should be', $element));
        }

        $tags = [];
        $tags_value = $select->getAttribute('data-value');
        if (!is_null($tags_value)) {
            $tags = array_map(
                function($tag) {
                    return $tag['name'];
                },
                json_decode($tags_value, true)
            );
        }

        $expectedTags = explode(',', $list_tags);
        if (array_diff($tags, $expectedTags) !== array_diff($expectedTags, $tags)) {
            throw new \Exception(sprintf('Element "%s" should contain "%s" but contains "%s".', $element, implode('|', $expectedTags), implode('|', $tags)));
        }
    }

    /**
     * @Then the :field_name field date should contain :date
     */
    public function theFieldDateShouldContain(string $field_name, string $date)
    {
        $field = $this->findField($field_name);
        if (is_null($field)) {
            throw new \Exception(sprintf('Field "%s" not found but should be', $field_name));
        }

        if ($date === 'now') {
            $date = (new \DateTime())->format('Y-m-d');
        }

        if ($field->getValue() !== $date) {
            throw new \Exception(sprintf('Field "%s" should contain "%s" but contains "%s"', $field_name, $field->getValue(), $date));
        }
    }

    /**
     * @Then I restart my browser
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
