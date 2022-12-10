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
use Phyxo\Conf;

class ImageStandardParams
{
    private string $conf_key = 'derivatives';

    final public const IMG_ORIGINAL = 'original';

    final public const IMG_SQUARE = 'square';
    final public const IMG_THUMB = 'thumb';
    final public const IMG_XXSMALL = '2small';
    final public const IMG_XSMALL = 'xsmall';
    final public const IMG_SMALL = 'small';
    final public const IMG_MEDIUM = 'medium';
    final public const IMG_LARGE = 'large';
    final public const IMG_XLARGE = 'xlarge';
    final public const IMG_XXLARGE = 'xxlarge';
    final public const IMG_CUSTOM = 'custom';

    private $conf, $derivatives;
    private $all_type_map = [], $type_map = [], $watermark = [], $customs = [], $quality = 95, $undefined_type_map = [];

    private  array $all_types = [
        self::IMG_SQUARE, self::IMG_THUMB, self::IMG_XXSMALL, self::IMG_XSMALL, self::IMG_SMALL,
        self::IMG_MEDIUM, self::IMG_LARGE, self::IMG_XLARGE, self::IMG_XXLARGE
    ];

    public function __construct(Conf $conf)
    {
        $this->conf = $conf;

        $this->derivatives = $conf[$this->conf_key];

        $this->loadFromConf();
    }

    public function getDefaultSizes(): array
    {
        $derivatives = [
            self::IMG_SQUARE => new DerivativeParams(SizingParams::square(120)),
            self::IMG_THUMB => new DerivativeParams(SizingParams::classic(144, 144)),
            self::IMG_XXSMALL => new DerivativeParams(SizingParams::classic(240, 240)),
            self::IMG_XSMALL => new DerivativeParams(SizingParams::classic(432, 324)),
            self::IMG_SMALL => new DerivativeParams(SizingParams::classic(576, 432)),
            self::IMG_MEDIUM => new DerivativeParams(SizingParams::classic(792, 594)),
            self::IMG_LARGE => new DerivativeParams(SizingParams::classic(1008, 756)),
            self::IMG_XLARGE => new DerivativeParams(SizingParams::classic(1224, 918)),
            self::IMG_XXLARGE => new DerivativeParams(SizingParams::classic(1656, 1242)),
        ];
        $now = time();
        foreach ($derivatives as $params) {
            $params->last_mod_time = $now;
        }

        return $derivatives;
    }

    public function getAllTypes(): array
    {
        return $this->all_types;
    }

    public function getDefinedTypeMap(): array
    {
        return $this->type_map;
    }

    public function getByType(string $type): DerivativeParams
    {
        return $this->all_type_map[$type];
    }

    public function getQuality()
    {
        return $this->quality;
    }

    public function setQuality(int $quality)
    {
        $this->quality = $quality;
    }

    public function setWatermark(WatermarkParams $watermark)
    {
        $this->watermark = $watermark;
    }

    public function getWatermark()
    {
        return $this->watermark;
    }

    public function getCustoms()
    {
        return $this->customs;
    }

    public function hasCustom(string $key): bool
    {
        return isset($this->customs[$key]);
    }

    public function unsetCustom(string $custom)
    {
        if (isset($this->customs[$custom])) {
            unset($this->customs[$custom]);
            $this->save();
        }
    }

    public function getUndefinedTypeMap(): array
    {
        return $this->undefined_type_map;
    }

    public function makeCustom(int $w, int $h, int $crop = 0, $minw = null, $minh = null): DerivativeParams
    {
        $params = new DerivativeParams(new SizingParams([$w, $h], $crop, [$minw, $minh]));
        $this->applyWatermark($params);

        $key = [];
        $params->add_url_tokens($key);
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
     * This is useful for any plugin/theme to just use $deriv[IMG_XLARGE] even if
     * the XLARGE is disabled.
     *
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
            if (DerivativeParams::derivative_to_url($type) === $derivative) {
                $derivative_params = $params;
            }
        }

        return $derivative_params;
    }

    /**
     * Returns an instance of DerivativeImage for a specific image and size.
     * Disabled derivatives fallback to an enabled derivative.
     *
     * @param string $type standard derivative param type (e.g. IMG_*)
     * @return DerivativeImage|null null if $type not found
     */
    public function getOne($type, Image $image)
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

    protected function loadFromConf()
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

    public function applyWatermark(DerivativeParams $params)
    {
        $params->use_watermark = !empty($this->watermark->file) && ($this->watermark->min_size[0] <= $params->sizing->ideal_size[0] || $this->watermark->min_size[1] <= $params->sizing->ideal_size[1]);
    }

    protected function buildMaps()
    {
        foreach ($this->type_map as $type => $params) {
            $params->type = $type;
            $this->applyWatermark($params);
        }

        $this->all_type_map = $this->type_map;

        for ($i = 0; $i < count($this->all_types); $i++) {
            $tocheck = $this->all_types[$i];
            if (!isset($this->type_map[$tocheck])) {
                for ($j = $i - 1; $j >= 0; $j--) {
                    $target = $this->all_types[$j];
                    if (isset($this->type_map[$target])) {
                        $this->all_type_map[$tocheck] = $this->type_map[$target];
                        $this->undefined_type_map[$tocheck] = $target;
                        break;
                    }
                }
            }
        }
    }

    public function setAndSave(array $map)
    {
        $this->type_map = $map;
        $this->save();

        $this->buildMaps();
    }

    public function save()
    {
        $conf_derivatives = [
            'd' => $this->type_map,
            'q' => $this->quality,
            'w' => $this->watermark,
            'c' => $this->customs,
        ];

        $this->conf->addOrUpdateParam($this->conf_key, $conf_derivatives, 'base64');
    }
}
