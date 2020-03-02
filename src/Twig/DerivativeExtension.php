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

namespace App\Twig;

use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DerivativeExtension extends AbstractExtension
{
    private $image_std_params;

    public function __construct(ImageStandardParams $image_std_params)
    {
        $this->image_std_params = $image_std_params;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('define_derivative', [$this, 'defineDerivative']),
            new TwigFunction('define_derivative_square', [$this, 'defineDerivativeSquare']),
            new TwigFunction('derivative_from_image', [$this, 'derivativeFromImage'])
        ];
    }

    /**
     * The "defineDerivative" function allows to define derivative from tpl file.
     * It assigns a DerivativeParams object to _name_ template variable.
     *
     * @param array $params
     *    - name (required)
     *    - type (optional)
     *    - width (required if type is empty)
     *    - height (required if type is empty)
     *    - crop (optional, used if type is empty)
     *    - min_height (optional, used with crop)
     *    - min_height (optional, used with crop)
     */
    public function defineDerivative(array $params)
    {
        if (isset($params['type'])) {
            return $this->image_std_params->getByType($params['type']);
        }

        if (empty($params['width'])) {
            throw new \Exception('define_derivative missing width');
        }

        if (empty($params['height'])) {
            throw new  \Exception('define_derivative missing height');
        }

        $w = intval($params['width']);
        $h = intval($params['height']);
        $crop = 0;
        $minw = null;
        $minh = null;

        if (isset($params['crop'])) {
            if (is_bool($params['crop'])) {
                $crop = $params['crop'] ? 1 : 0;
            } else {
                $crop = round($params['crop'] / 100, 2);
            }

            if ($crop) {
                $minw = empty($params['min_width']) ? $w : intval($params['min_width']);
                if ($minw > $w) {
                    throw new \Exception('define_derivative invalid min_width');
                }

                $minh = empty($params['min_height']) ? $h : intval($params['min_height']);

                if ($minh > $h) {
                    throw new \Exception('define_derivative invalid min_height');
                }
            }
        }

        return $this->image_std_params->makeCustom($w, $h, $crop, $minw, $minh);
    }

    public function defineDerivativeSquare()
    {
        return $this->image_std_params->getByType(\Phyxo\Image\ImageStandardParams::IMG_SQUARE);
    }

    public function derivativeFromImage(array $params = [])
    {
        if (empty($params['image']) || empty($params['params'])) {
            return;
        }

        return new DerivativeImage($params['image'], $params['params'], $this->image_std_params);
    }
}
