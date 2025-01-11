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

namespace App\Tests\Phyxo\Image;

use App\Enum\ConfEnum;
use App\Enum\ImageSizeType;
use App\Repository\ConfigRepository;
use PHPUnit\Framework\TestCase;
use Phyxo\Conf;
use Phyxo\Image\DerivativeParams;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SizingParams;
use Prophecy\PhpUnit\ProphecyTrait;

class ImageStandardParamsTest extends TestCase
{
    use ProphecyTrait;
    private array $allTypes = [];

    protected function setUp(): void
    {
        $this->allTypes = [
            ImageSizeType::SQUARE->value => new DerivativeParams(SizingParams::square(120)),
            ImageSizeType::THUMB->value => new DerivativeParams(SizingParams::classic(144, 144)),
            ImageSizeType::XXSMALL->value => new DerivativeParams(SizingParams::classic(240, 240)),
            ImageSizeType::XSMALL->value => new DerivativeParams(SizingParams::classic(432, 324)),
            ImageSizeType::SMALL->value => new DerivativeParams(SizingParams::classic(576, 432)),
            ImageSizeType::MEDIUM->value => new DerivativeParams(SizingParams::classic(792, 594)),
            ImageSizeType::LARGE->value => new DerivativeParams(SizingParams::classic(1008, 756)),
            ImageSizeType::XLARGE->value => new DerivativeParams(SizingParams::classic(1224, 918)),
            ImageSizeType::XXLARGE->value => new DerivativeParams(SizingParams::classic(1656, 1242)),
        ];
    }

    public function testBuildMaps()
    {
        $configRepository = $this->prophesize(ConfigRepository::class);
        $conf = new Conf($configRepository->reveal());

        $selectedTypes = array_rand($this->allTypes, random_int(2, count($this->allTypes)));

        $map = [];
        foreach ($selectedTypes as $type) {
            $map[$type] = $this->allTypes[$type];
        }

        $conf->addOrUpdateParam('derivatives', ['d' => $map], ConfEnum::JSON);
        $imageStandardParams = new ImageStandardParams($conf);

        $this->assertEquals($map, $imageStandardParams->getDefinedTypeMap());
    }

    public function testSomeDerivatives()
    {
        $configRepository = $this->prophesize(ConfigRepository::class);
        $conf = new Conf($configRepository->reveal());

        $imageStandardParams = new ImageStandardParams($conf);

        $map = [
            ImageSizeType::SQUARE->value => new DerivativeParams(SizingParams::square(120)),
            ImageSizeType::THUMB->value => new DerivativeParams(SizingParams::classic(144, 144)),
            ImageSizeType::MEDIUM->value => new DerivativeParams(SizingParams::classic(792, 594)),
            ImageSizeType::LARGE->value => new DerivativeParams(SizingParams::classic(1008, 756)),
        ];
        $imageStandardParams->setAndSave($map);

        $this->assertEquals($map, $imageStandardParams->getDefinedTypeMap());
    }
}
