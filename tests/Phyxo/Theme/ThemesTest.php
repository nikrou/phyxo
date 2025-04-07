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

namespace App\Tests\Phyxo\Theme;

use App\DataMapper\UserMapper;
use App\Entity\User;
use App\Repository\ThemeRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Phyxo\Theme\Themes;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Filesystem\Filesystem;

class ThemesTest extends TestCase
{
    use ProphecyTrait;
    final public const string THEMES_PATH = __DIR__ . '/../../fixtures/themes';
    final public const string THEMES_DIR = __DIR__ . '/../../tmp/themes';

    protected function setUp(): void
    {
        $fs = new Filesystem();
        $fs->mkdir(self::THEMES_DIR);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove(self::THEMES_DIR);
    }

    private function mirrorToWorkspace(): string
    {
        $workspace = self::THEMES_DIR . '/' . md5(random_bytes(15));
        $fs = new Filesystem();
        $fs->mkdir($workspace);
        $fs->mirror(self::THEMES_PATH, $workspace);

        return $workspace;
    }

    public function testFsThemes(): void
    {
        $workspace = $this->mirrorToWorkspace();
        $user = $this->prophesize(User::class);
        $user->getLocale()->willReturn('en_GB');

        $userMapper = $this->prophesize(UserMapper::class);
        $userMapper->getUser()->willReturn($user->reveal());

        $themeRepository = $this->prophesize(ThemeRepository::class);
        $themes = new Themes($themeRepository->reveal(), $userMapper->reveal());
        $themes->setRootPath($workspace);

        $this->assertEquals($this->getLocalThemes(), $themes->getFsThemes());
    }

    #[DataProvider('sortThemesDataProvider')]
    public function testSortThemes(string $sort_type, array $order): void
    {
        $workspace = $this->mirrorToWorkspace();

        $user = $this->prophesize(User::class);
        $user->getLocale()->willReturn('en_GB');

        $userMapper = $this->prophesize(UserMapper::class);
        $userMapper->getUser()->willReturn($user->reveal());

        $themeRepository = $this->prophesize(ThemeRepository::class);

        $themes = new Themes($themeRepository->reveal(), $userMapper->reveal());
        $themes->setRootPath($workspace);

        $themes->sortFsThemes($sort_type);

        $this->assertEquals($this->getLocalThemes(), $themes->getFsThemes());
        $this->assertEquals($order, array_keys($themes->getFsThemes()));
    }

    public static function sortThemesDataProvider(): array
    {
        return [
            ['author', ['theme2', 'theme3', 'theme4', 'my theme dir with space', 'theme1']],
            ['id', ['my theme dir with space', 'theme1', 'theme2', 'theme3', 'theme4']],
            ['status', ['my theme dir with space', 'theme1', 'theme3', 'theme4', 'theme2']],
            ['name', ['my theme dir with space', 'theme1', 'theme3', 'theme4', 'theme2']],
        ];
    }

    private function getLocalThemes(): array
    {
        return [
            'my theme dir with space' => [
                'id' => 'my theme dir with space',
                'name' => 'A simple theme',
                'version' => '1.2.3',
                'extension' => 123,
                'uri' => 'https://ext.phyxo.net/extension_view.php?eid=123',
                'description' => 'Simple Number One',
                'author' => 'Nicolas',
                'admin_uri' => false,
                'author_uri' => 'https://www.phyxo.net',
            ],
            'theme1' => [
                'id' => 'theme1',
                'name' => 'A simple theme',
                'version' => '1.2.3',
                'extension' => 123,
                'uri' => 'https://ext.phyxo.net/extension_view.php?eid=123',
                'description' => 'Simple Number One',
                'author' => 'Nicolas',
                'author_uri' => 'https://www.phyxo.net',
                'admin_uri' => false,
            ],
            'theme2' => [
                'id' => 'theme2',
                'name' => 'ZZ Theme',
                'version' => '4.5.6',
                'extension' => 456,
                'uri' => 'https://ext.phyxo.net/extension_view.php?eid=456',
                'description' => 'Theme mobile without author uri',
                'author' => 'Arthur',
                'admin_uri' => false,
            ],
            'theme3' => [
                'id' => 'theme3',
                'name' => 'My first theme',
                'version' => '7.8.9',
                'extension' => 789,
                'uri' => 'https://ext.phyxo.net/extension_view.php?eid=789',
                'description' => 'Simple Number Three',
                'author' => 'Jean',
                'author_uri' => 'https://www.phyxo.net',
                'admin_uri' => false,
            ],
            'theme4' => [
                'id' => 'theme4',
                'name' => 'Photos Theme',
                'version' => '10.11.12',
                'extension' => 10,
                'uri' => 'https://ext.phyxo.net/extension_view.php?eid=10',
                'description' => 'Simple Number Four',
                'author' => 'Jean',
                'author_uri' => 'https://www.phyxo.net',
                'admin_uri' => false,
            ],
        ];
    }
}
