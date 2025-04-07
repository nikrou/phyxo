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

namespace Phyxo\Image;

use App\Entity\Image;
use App\Enum\ConfEnum;
use App\Enum\ImageSizeType;
use Phyxo\Conf;

class ImageStandardParams
{
    private const string CONF_KEY = 'derivatives';

    /**
     * @var array{'d'?: array<string, DerivativeParams>, 'q'?: int, 'w'?: WatermarkParams, 'c'?: array<string, int>}
     */
    private $derivatives;

    /** @var array<string, DerivativeParams> */
    private array $all_type_map = [];

    /** @var array<string, DerivativeParams> */
    private array $type_map = [];
    private WatermarkParams $watermark;

    /** @var array<string, int> */
    private array $customs = [];
    private int $quality = 95;

    /** @var array<string, string> */
    private $undefined_type_map = [];

    public function __construct(private Conf $conf)
    {
        $this->derivatives = $this->conf[self::CONF_KEY];

        $this->loadFromConf();
    }

    /**
     * @return array<string, DerivativeParams>
     */
    public function getDefaultSizes(): array
    {
        $derivatives = [
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

        $now = time();
        foreach ($derivatives as $params) {
            $params->last_mod_time = $now;
        }

        return $derivatives;
    }

    /**
     * @return array<string, DerivativeParams>
     */
    public function getDefinedTypeMap(): array
    {
        return $this->type_map;
    }

    public function getByType(ImageSizeType $type): DerivativeParams
    {
        return $this->all_type_map[$type->value];
    }

    public function getQuality(): int
    {
        return $this->quality;
    }

    public function setQuality(int $quality): void
    {
        $this->quality = $quality;
    }

    public function setWatermark(WatermarkParams $watermark): void
    {
        $this->watermark = $watermark;
    }

    public function getWatermark(): WatermarkParams
    {
        return $this->watermark;
    }

    /**
     * @return array<string, int>
     */
    public function getCustoms(): array
    {
        return $this->customs;
    }

    public function hasCustom(string $key): bool
    {
        return isset($this->customs[$key]);
    }

    public function unsetCustom(string $custom): void
    {
        if (isset($this->customs[$custom])) {
            unset($this->customs[$custom]);
            $this->save();
        }
    }

    /**
     * @return array<string, string>
     */
    public function getUndefinedTypeMap(): array
    {
        return $this->undefined_type_map;
    }

    public function makeCustom(int $w, int $h, int $crop = 0, ?int $minw = null, ?int $minh = null): DerivativeParams
    {
        $params = new DerivativeParams(new SizingParams([$w, $h], $crop, [$minw, $minh]));
        $this->applyWatermark($params);

        $key = [];
        $params->addUrlTokens($key);
        $key = implode('_', $key);
        if (!isset($this->customs[$key]) || $this->customs[$key] < time() - 24 * 3600) {
            $this->customs[$key] = time();
            $this->save();
        }

        return $params;
    }

    /**
     * Return associative an array of all DerivativeImage for a specific image.
     * Disabled derivative types can be still found in the return, mapped to an
     * enabled derivative (e.g. the values are not unique in the return array).
     * This is useful for any plugin/theme to just use $deriv[XLARGE] even if
     * the XLARGE is disabled.
     *
     * @return array<string, DerivativeImage>
     */
    public function getAll(Image $image): array
    {
        $ret = [];
        // build enabled types
        foreach ($this->getDefinedTypeMap() as $type => $params) {
            $derivative = new DerivativeImage($image, $params, $this);
            $ret[$type] = $derivative;
        }

        // disabled types, fallback to enabled types
        foreach ($this->getUndefinedTypeMap() as $type => $type2) {
            $ret[$type] = $ret[$type2];
        }

        return $ret;
    }

    public function getParamsFromDerivative(string $derivative): ?DerivativeParams
    {
        $derivative_params = null;

        foreach ($this->getDefinedTypeMap() as $type => $params) {
            if (DerivativeParams::derivativeToUrl($type) === $derivative) {
                $derivative_params = $params;
            }
        }

        return $derivative_params;
    }

    /**
     * Returns an instance of DerivativeImage for a specific image and size.
     * Disabled derivatives fallback to an enabled derivative.
     *
     * @param string $type standard derivative param type (e.g. ImageSizeType::*)
     *
     * @return DerivativeImage|null null if $type not found
     */
    public function getOne($type, Image $image): ?DerivativeImage
    {
        $defined = $this->getDefinedTypeMap();
        if (isset($defined[$type])) {
            return new DerivativeImage($image, $defined[$type], $this);
        }

        $undefined = $this->getUndefinedTypeMap();
        if (isset($undefined[$type])) {
            return new DerivativeImage($image, $defined[$undefined[$type]], $this);
        }

        return null;
    }

    protected function loadFromConf(): void
    {
        if (!empty($this->derivatives) && isset($this->derivatives['d'])) {
            $this->type_map = $this->derivatives['d'];
            $this->watermark = $this->derivatives['w'] ?? new WatermarkParams();
            if (isset($this->derivatives['c'])) {
                $this->customs = $this->derivatives['c'];
            }

            if (isset($this->derivatives['q'])) {
                $this->quality = $this->derivatives['q'];
            }
        } else {
            $this->watermark = new WatermarkParams();
            $this->type_map = $this->getDefaultSizes();
            $this->save();
        }

        $this->buildMaps();
    }

    public function applyWatermark(DerivativeParams $params): void
    {
        $params->use_watermark = $this->watermark->getFile() !== ''
            && ($this->watermark->getMinSize()[0] <= $params->getSizing()->getIdealSize()[0]
            || $this->watermark->getMinSize()[1] <= $params->getSizing()->getIdealSize()[1]);
    }

    protected function buildMaps(): void
    {
        foreach ($this->type_map as $type => $params) {
            $params->type = ImageSizeType::from($type);
            $this->applyWatermark($params);
        }

        $this->all_type_map = $this->type_map;
        $counter = count(ImageSizeType::getAllTypes());

        for ($i = 0; $i < $counter; $i++) {
            $tocheck = ImageSizeType::getAllTypes()[$i]->value;
            if (!isset($this->type_map[$tocheck])) {
                for ($j = $i - 1; $j >= 0; $j--) {
                    $target = ImageSizeType::getAllTypes()[$j]->value;
                    if (isset($this->type_map[$target])) {
                        $this->all_type_map[$tocheck] = $this->type_map[$target];
                        $this->undefined_type_map[$tocheck] = $target;
                        break;
                    }
                }
            }
        }
    }

    /**
     * @param array<string, DerivativeParams> $map
     */
    public function setAndSave(array $map): void
    {
        $this->type_map = $map;
        $this->save();

        $this->buildMaps();
    }

    public function save(): void
    {
        $conf_derivatives = [
            'd' => $this->type_map,
            'q' => $this->quality,
            'w' => $this->watermark,
            'c' => $this->customs,
        ];

        $this->conf->addOrUpdateParam(self::CONF_KEY, $conf_derivatives, ConfEnum::BASE64);
    }
}
