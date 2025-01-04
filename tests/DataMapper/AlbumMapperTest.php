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

namespace App\Tests\DataMapper;

use App\DataMapper\AlbumMapper;
use App\Enum\UserPrivacyLevelType;
use App\Repository\AlbumRepository;
use App\Repository\ImageAlbumRepository;
use App\Repository\ImageRepository;
use App\Repository\UserCacheAlbumRepository;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Phyxo\Conf;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AlbumMapperTest extends TestCase
{
    use ProphecyTrait;

    public function testComputedAlbum(): void
    {
        $albums = [
            [
                'album_id' => 1,
                'id_uppercat' => null,
                'date_last' => null,
                'nb_images' => 0,
            ],
            [
                'album_id' => 2,
                'id_uppercat' => null,
                'date_last' => null,
                'nb_images' => 3,
            ],
            [
                'album_id' => 11,
                'id_uppercat' => 1,
                'date_last' => null,
                'nb_images' => 0,
            ],
            [
                'album_id' => 21,
                'id_uppercat' => 2,
                'date_last' => null,
                'nb_images' => 0,
            ],
            [
                'album_id' => 3,
                'id_uppercat' => null,
                'date_last' => '2013-02-23 10:10:23',
                'nb_images' => 2,
            ],
            [
                'album_id' => 30,
                'id_uppercat' => 3,
                'date_last' => '2012-11-14 17:43:38',
                'nb_images' => 25,
            ],
            [
                'album_id' => 40,
                'id_uppercat' => 3,
                'date_last' => '2012-11-15 10:03:20',
                'nb_images' => 5,
            ],
        ];

        $albumRepository = $this->prophesize(AlbumRepository::class);
        $albumRepository->getComputedAlbums(Argument::any(), Argument::type('array'))->willReturn($albums);

        $level = UserPrivacyLevelType::DEFAULT;
        $forbidden_albums = [];
        $albumMapper = new AlbumMapper(
            $this->prophesize(Conf::class)->reveal(),
            $albumRepository->reveal(),
            $this->prophesize(RouterInterface::class)->reveal(),
            $this->prophesize(TranslatorInterface::class)->reveal(),
            $this->prophesize(UserRepository::class)->reveal(),
            $this->prophesize(UserCacheAlbumRepository::class)->reveal(),
            $this->prophesize(ImageAlbumRepository::class)->reveal(),
            $this->prophesize(ImageRepository::class)->reveal()
        );

        $expectedComputedAlbums = [
            1 => [
                'album_id' => 1,
                'id_uppercat' => null,
                'date_last' => null,
                'nb_images' => 0,
                'nb_categories' => 1,
                'count_categories' => 1,
                'count_images' => 0,
                'max_date_last' => null,
            ],
            2 => [
                'album_id' => 2,
                'id_uppercat' => null,
                'date_last' => null,
                'nb_images' => 3,
                'nb_categories' => 1,
                'count_categories' => 1,
                'count_images' => 3,
                'max_date_last' => null,
            ],
            11 => [
                'album_id' => 11,
                'id_uppercat' => 1,
                'date_last' => null,
                'nb_images' => 0,
                'nb_categories' => 0,
                'count_categories' => 0,
                'count_images' => 0,
                'max_date_last' => null,
            ],
            21 => [
                'album_id' => 21,
                'id_uppercat' => 2,
                'date_last' => null,
                'nb_images' => 0,
                'nb_categories' => 0,
                'count_categories' => 0,
                'count_images' => 0,
                'max_date_last' => null,
            ],
            3 => [
                'album_id' => 3,
                'id_uppercat' => null,
                'date_last' => '2013-02-23 10:10:23',
                'nb_images' => 2,
                'nb_categories' => 2,
                'count_categories' => 2,
                'count_images' => 32,
                'max_date_last' => '2013-02-23 10:10:23',
            ],
            30 => [
                'album_id' => 30,
                'id_uppercat' => 3,
                'date_last' => '2012-11-14 17:43:38',
                'nb_images' => 25,
                'nb_categories' => 0,
                'count_categories' => 0,
                'count_images' => 25,
                'max_date_last' => '2012-11-14 17:43:38',
            ],
            40 => [
                'album_id' => 40,
                'id_uppercat' => 3,
                'date_last' => '2012-11-15 10:03:20',
                'nb_images' => 5,
                'nb_categories' => 0,
                'count_categories' => 0,
                'count_images' => 5,
                'max_date_last' => '2012-11-15 10:03:20',
            ],
        ];

        $computedAlbums = $albumMapper->getComputedAlbums($level, $forbidden_albums);

        $this->assertEquals($computedAlbums, $expectedComputedAlbums);
    }
}
