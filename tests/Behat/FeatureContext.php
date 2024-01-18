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

use Exception;
use DateTime;
use Behat\Gherkin\Node\PyStringNode;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class FeatureContext extends BaseContext
{
    public function __construct(private readonly KernelInterface $kernel, private readonly Storage $storage)
    {
    }

    protected function getContainer():  ContainerInterface
    {
        return $this->kernel->getContainer()->get('test.service_container');
    }

    /**
     * @Given I am logged in as :username with password :password
     */
    public function iAmLoggedInAsWithPassword(string $username, string $password, bool $remember = false): void
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
    public function iAmLoggedInAsWithPasswordAndAutoLogin(string $username, string $password): void
    {
        $this->iAmLoggedInAsWithPassword($username, $password, true);
        $this->getMink()->assertSession()->cookieExists($this->getCookieName());
    }

    /**
     * @Then I should be allowed to go to a protected page
     */
    public function iShouldBeAllowedToGoToAProtectedPage(): void
    {
        $this->visit($this->getContainer()->get('router')->generate('profile'));
    }

    /**
     * @Then I should not be allowed to go to a protected page
     */
    public function iShouldNotBeAllowedToGoToAProtectedPage(): void
    {
        $this->visit($this->getContainer()->get('router')->generate('profile'));
        $this->getMink()->assertSession()->statusCodeEquals(403);
    }

    /**
     * @Then I should not be allowed to go to album :album_name
     */
    public function iShouldNotBeAllowedToGoToAlbum(string $album_name): void
    {
        $this->visit($this->getContainer()->get('router')->generate('album', ['album_id' => $this->storage->get('album_' . $album_name)->getId()]));
        $this->getMink()->assertSession()->statusCodeEquals(403);
        $this->getMink()->assertSession()->pageTextContains('The server returned a "403 Forbidden".');
    }

    protected function isPhotoInPage(int $image_id): bool
    {
        return $this->getPage()->find('css', '[data-photo-id="' . $image_id . '"]') !== null;
    }

    /**
     * @Then I should see photo :photo_name
     */
    public function iShouldSeePhoto(string $photo_name): void
    {
        $image_id = $this->storage->get('image_' . $photo_name)->getId();
        if (!$this->isPhotoInPage($image_id)) {
            throw new Exception(sprintf('Photo "%s" not found in the page', $photo_name));
        }
    }

    /**
     * @Then I should not see photo :photo_name
     */
    public function iShouldNotSeePhoto(string $photo_name): void
    {
        $image_id = $this->storage->get('image_' . $photo_name)->getId();
        if ($this->isPhotoInPage($image_id)) {
            throw new Exception(sprintf('Photo "%s" was found in the page but should not', $photo_name));
        }
    }

    protected function beAbleToEditTags(): bool
    {
        return $this->getPage()->find('css', '.edit-tags') !== null;
    }

    /**
     * @Then I should not be able to edit tags
     */
    public function iShouldNotBeAbleToEditTags(): void
    {
        if ($this->beAbleToEditTags()) {
            throw new Exception('User can edit tags but should not');
        }
    }

    /**
     * @Then I should be able to edit tags
     */
    public function iShouldBeAbleToEditTags(): void
    {
        if (!$this->beAbleToEditTags()) {
            throw new Exception('User cannot edit tags but should be able to');
        }
    }

    /**
     * @Then I should see tag :tag
     */
    public function iShouldSeeTag(string $tag_name): void
    {
        $tags = $this->getPage()->find('css', '#Tags');
        if ($tags === null) {
            throw new Exception('No tags found on the page');
        }

        $tag_link = $tags->find('xpath', '//*[contains(text(), "' . $tag_name . '")]');
        if ($tag_link === null) {
            throw new Exception(sprintf('Tag "%s" not found on the page but should be', $tag_name));
        }
    }

    /**
     * @Then I should not see tag :tag
     */
    public function iShouldNotSeeTag(string $tag_name): void
    {
        $tags = $this->getPage()->find('css', '#Tags');
        if ($tags !== null) {
            $tag_link = $tags->find('xpath', '//*[contains(text(), "' . $tag_name . '")]');
            if ($tag_link !== null) {
                throw new Exception(sprintf('Tag "%s" found on the page but should not be', $tag_name));
            }
        }
    }

    /**
     * @When I should see link :link_label
     * @Then I should see link :link_label in :parent
     */
    public function iShouldSeeLink(string $link_label, string $parent = ''): void
    {
        if (!empty($parent)) {
            $parentNode = $this->getSession()->getPage()->find('css', $parent);
        } else {
            $parentNode = $this->getSession()->getPage();
        }
        $link = $parentNode->findLink($link_label);

        if ($link === null) {
            throw new Exception(sprintf('Link "%s" not found on the page but should be', $link_label));
        }
    }

    /**
     * @When I should not see link :link_label
     */
    public function iShouldNotSeeLink(string $link_label): void
    {
        $link = $this->findLink($link_label);

        if ($link !== null) {
            throw new Exception(sprintf('Link "%s" found on the page but should not be', $link_label));
        }
    }

    /**
     * @Then I should see :description for :album_name description
     */
    public function iShouldSeeDescriptionForAlbum(string $description, string $album_name): void
    {
        $album = $this->getPage()->find('css', sprintf('*[data-id="%d"]', $this->storage->get('album_' . $album_name)->getId()));
        if ($description !== $this->findByDataTestid('album-description', $album)->getText()) {
            throw new Exception(sprintf('Description "%s" for album "%s" not found but should be', $description, $album_name));
        }
    }

    /**
     * @Then I should see :nb_images for :album_name number of images
     */
    public function iShouldSeeNumberOfImagesForAlbum(string $nb_images, string $album_name): void
    {
        $album = $this->getPage()->find('css', sprintf('*[data-id="%d"]', $this->storage->get('album_' . $album_name)->getId()));
        $element = $this->findByDataTestid('album-nb-images', $album);

        // @FIX:  Element visibility check is not supported by Behat\Symfony2Extension\Driver\KernelDriver
        // if (!$element->isVisible()) {
        //     throw new \Exception('Number of images exists but it is not visible');
        // }

        if ($nb_images !== $element->getText()) {
            throw new Exception(sprintf('Number of images "%s" for album "%s" not found but should be', $nb_images, $album_name));
        }
    }

    /**
     * admin
     * @Then I should see :nb_images for album :album_name
     */
    public function iShouldSeeTextNumberOfImagesForAlbum(string $nb_images, string $album_name): void
    {
        $div_album = $this->getPage()->find('css', sprintf('#album-%d', $this->storage->get('album_' . $album_name)->getId()));
        $element = $div_album->find('css', '*[data-testid="number_of_photos"]');

        if ($nb_images !== $element->getText()) {
            throw new Exception(sprintf('Number of images "%s" for album "%s" not found but should be', $nb_images, $album_name));
        }
    }

    /**
     * @Then I select :image_name in thumbnails
     */
    public function iSelectPhotoInThumbnails(string $image_name): void
    {
        $this->getPage()->find('css', sprintf('*[data-testid="image-%d"] input[type="checkbox"]', $this->storage->get('image_' . $image_name)->getId()))->check();
    }

    /**
     * @Given I follow image of type :type_map
     */
    public function iFollowImageOfType(string $type_map): void
    {
        $picture_derivatives = $this->getPage()->find('css', '*[data-testid="picture.derivatives"]');
        if (is_null($picture_derivatives)) {
            throw new Exception("Cannot find picture derivatives");
        }

        // $derivative = $picture_derivatives->find('css', sprintf('*[data-type-save="%s"]', $type_map));
        $derivative = $picture_derivatives->find('css', sprintf('#derivative%s', $type_map));

        if (is_null($derivative)) {
            throw new Exception("Cannot find image of size {$type_map}");
        }

        $this->visit($derivative->getAttribute('data-url'));
    }

    /**
     * @When I add a comment :
     */
    public function iAddAComment(PyStringNode $comment): void
    {
        $this->fillField('Comment', $comment);
        $this->pressButton('Submit');
    }

    /**
     * @Then the option :option from :from" is selected
     */
    public function theOptionFromSelectIsSelected(string $option, string $from): void
    {
        $selectField = $this->findField($from);

        if ($selectField === null) {
            throw new Exception(sprintf('The select "%s" was not found in the page', $from));
        }

        $optionField = $selectField->find('xpath', "//option[@selected]");
        if ($optionField === null) {
            throw new Exception(sprintf('No option is selected in the %s select', $from));
        }

        if ($optionField->getValue() !== $option) {
            throw new Exception(sprintf('The option "%s" was not selected but should be', $option));
        }
    }

    /**
     * @Then the radio button :value from :name should be selected
     */
    public function theRadioButtonFromShouldBeSelected(string $value, string $name): void
    {
        $radioButton = $this->getSession()->getPage()->find('css', sprintf('input[type="radio"][name="%s"][value="%s"]', $name, $value));

        if (is_null($radioButton)) {
            throw new Exception(sprintf('Button radio "%s" with value "%s" not found but should be', $name, $value));
        }

        if (!$radioButton->isChecked()) {
            throw new Exception(sprintf('Button radio "%s" with value "%s" should be checked but is not', $name, $value));
        }
    }

    /**
     * @Then I should not see link to :href
     */
    public function iShouldNotSeeLinkTo(string $href): void
    {
        $link = $this->findLink($href);
        if (!is_null($link)) {
            throw new Exception(sprintf('Link with href "%s" was found but shoud not', $link->getAttribute('href')));
        }
    }

    /**
     * @Then the select :element should contain:
     */
    public function theSelectShouldContain(string $element, PyStringNode $expectedString): void
    {
        $select = $this->findField($element);
        if (is_null($select)) {
            throw new Exception(sprintf('Select "%s" not found but should be', $element));
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
            throw new Exception(sprintf('Element "%s" should contain "%s" but contains "%s".', $element, implode('|', $expectedOptions), implode('|', $options)));
        }
    }

    /**
     * @Then the group :group should have members :users
     */
    public function theGroupShouldHaveMembers(string $group, string $users): void
    {
        $rowGroup = $this->getPage()->find('css', sprintf('table tr:contains("%s")', $group));
        if (is_null($rowGroup)) {
            throw new Exception(sprintf('Cannot find a group "%s" on the page', $group));
        }
        $members = $rowGroup->find('css', 'td[class="members"]');
        if (is_null($members)) {
            throw new Exception(sprintf('Cannot find members cell for group "%s" on the page', $group));
        }
        $foundMembers = explode(' ', (string) $members->getText());
        $expectedMembers = explode(',', $users);
        if (array_diff($foundMembers, $expectedMembers) !== array_diff($expectedMembers, $foundMembers)) {
            throw new Exception(sprintf('Members for group "%s" are "%s" but should be "%s".', $group, implode(',', $foundMembers), $users));
        }
    }

    /**
     * @Given I follow group :group permissions
     */
    public function iFollowGroupPermissions(string $group): void
    {
        $rowGroup = $this->getPage()->find('css', sprintf('table tr:contains("%s")', $group));
        if (is_null($rowGroup)) {
            throw new Exception(sprintf('Cannot find a group "%s" on the page', $group));
        }
        $rowGroup->clickLink('Permissions');
    }

    /**
     * @Given I follow album :album_name Edit
     */
    public function iFollowAlbumEdit(string $album_name): void
    {
        $album = $this->storage->get('album_' . $album_name);
        $divAlbum = $this->getPage()->find('css', sprintf('#album-%d', $album->getId()));
        if (is_null($divAlbum)) {
            throw new Exception(sprintf('Cannot find an album "%s" on the page', $album_name));
        }
        $divAlbum->clickLink('Edit');
    }

    /**
     * @Given I want to edit :image_name
     */
    public function iWantToEdit(string $image_name): void
    {
        $image = $this->storage->get('image_' . $image_name);
        $this->visit($this->getContainer()->get('router')->generate('admin_photo', ['image_id' => $image->getId()]));
    }

    /**
     * @Then linked albums :element should be album :album_name
     */
    public function linkedAlbumsShouldBeAlbum(string $element, string $album_name): void
    {
        $select = $this->findField($element);
        if (is_null($select)) {
            throw new Exception(sprintf('Select "%s" not found but should be', $element));
        }

        $options = [];
        $albums_value = $select->getAttribute('data-value');
        if (!is_null($albums_value)) {
            $options = json_decode((string) $albums_value, true);
        }

        $expectedOptions = [$this->storage->get('album_' . $album_name)->getId()];
        sort($options);
        sort($expectedOptions);

        if (array_diff($options, $expectedOptions) !== array_diff($expectedOptions, $options)) {
            throw new Exception(sprintf('Element "%s" should contain "%s" but contains "%s".', $element, implode('|', $expectedOptions), implode('|', $options)));
        }
    }

    /**
     * @Then tags :element should be :list_tags
     */
    public function tagsShouldBeAlbum(string $element, string $list_tags): void
    {
        $select = $this->findField($element);
        if (is_null($select)) {
            throw new Exception(sprintf('Select "%s" not found but should be', $element));
        }

        $tags = [];
        $tags_value = $select->getAttribute('data-value');
        if (!is_null($tags_value)) {
            $tags = array_map(
                fn($tag) => $tag['name'],
                json_decode((string) $tags_value, true)
            );
        }

        $expectedTags = explode(',', $list_tags);
        if (array_diff($tags, $expectedTags) !== array_diff($expectedTags, $tags)) {
            throw new Exception(sprintf('Element "%s" should contain "%s" but contains "%s".', $element, implode('|', $expectedTags), implode('|', $tags)));
        }
    }

    /**
     * Example: Then the "date_creation" field date should contain "now"
     *
     * @Then the :field_name field date should contain :date
     */
    public function theFieldDateShouldContain(string $field_name, string $date): void
    {
        $field = $this->findField($field_name);
        if (is_null($field)) {
            throw new Exception(sprintf('Field "%s" not found but should be', $field_name));
        }

        if ($date === 'now') {
            $date = (new DateTime())->format('Y-m-d');
        }

        if ($field->getValue() !== $date) {
            throw new Exception(sprintf('Field "%s" should contain "%s" but contains "%s"', $field_name, $field->getValue(), $date));
        }
    }

    /**
     * Example: When I follow "delete album" for album "album 1"
     *
     * @When I follow :link_label for album :album_name
     */
    public function iFollowLinkForAlbum(string $link_label, string $album_name): void
    {
        $album = $this->storage->get('album_' . $album_name);
        $divAlbum = $this->getPage()->find('css', sprintf('#album-%d', $album->getId()));
        if (is_null($divAlbum)) {
            throw new Exception(sprintf('Cannot find an album "%s" on the page', $album_name));
        }
        $divAlbum->clickLink($link_label);
    }

    /**
     * @Then I should see :nb_thumbnails calendar thumbnail
     * @Then I should see :nb_thumbnails calendar thumbnails
     */
    public function iShouldSeeCalendarThumbnail(int $nb_thumbnails): void
    {
        $divThumbnails = $this->getPage()->findAll('css', '.thumbnail');
        if (count($divThumbnails) !== $nb_thumbnails) {
            throw new Exception(sprintf('Number of thumbnails should be "%s" but "%s" found', $nb_thumbnails, count($divThumbnails)));
        }
    }

    /**
     * @Then the calendar thumbnail :datePart should contains :nb_images image
     * @Then the calendar thumbnail :datePart should contains :nb_images images
     */
    public function calendarThumbnailShouldContainsNImages(string $datePart, string $nb_images): void
    {
        $divThumbnail = $this->getPage()->find('css', sprintf('[data-testid="%s"] .number-of-images', $datePart));
        if (is_null($divThumbnail)) {
            throw new Exception(sprintf('Cannot find thumbnail with date part "%s" on the page', $datePart));
        }

        if ($nb_images !== $divThumbnail->getText()) {
            throw new Exception(sprintf('Number of images for date part "%s" should be "%s" but "%s" found', $datePart, $nb_images, $divThumbnail->getText()));
        }
    }

    /**
     * @Then there's :nb_images image for day :day
     * @Then there's :nb_images images for day :day
     */
    public function theresImageForDay(int $nb_images, int $day): void
    {
        $td = $this->getPage()->find('css', sprintf('[data-testid="day-%d"] .number-of-images', $day));
        if (is_null($td)) {
            throw new Exception(sprintf('Cannot find calendar cell with day "%d" on the page', $day));
        }

        if ($nb_images !== (int) $td->getText()) {
            throw new Exception(sprintf('Number of images for day "%d" should be "%d" but "%s" found', $day, $nb_images, $td->getText()));
        }
    }

    /**
     * @When I click calendar thumbnail :day
     */
    public function iClickCalendarThumbnail(string $day): void
    {
        $a_in_td = $this->getPage()->find('css', sprintf('[data-testid="day-%d"] a', $day));
        if (is_null($a_in_td)) {
            throw new Exception(sprintf('Cannot find calendar cell with day "%d" on the page', $day));
        }

        $a_in_td->click();
    }

    /**
     * @Then I restart my browser
     */
    public function iRestartMyBrowser(): void
    {
        $session = $this->getSession();
        $cookie = $session->getCookie($this->getCookieName());
        $session->restart();
        $session->setCookie($this->getCookieName(), $cookie);
    }

    private function getCookieName(): string
    {
        return $this->getContainer()->getParameter('remember_cookie'); // @TODO : retrieve from config
    }
}
