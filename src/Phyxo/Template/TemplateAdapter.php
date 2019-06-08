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

namespace Phyxo\Template;

use Phyxo\Image\DerivativeImage;
use Phyxo\Image\SrcImage;
use Phyxo\Image\DerivativeParams;
use Phyxo\Image\ImageStandardParams;

class TemplateAdapter
{
    public function derivative(SrcImage $img, DerivativeParams $params, ImageStandardParams $image_std_params): DerivativeImage
    {
        return new DerivativeImage($img, $params, $image_std_params);
    }

    public function derivative_url(SrcImage $img, DerivativeParams $params, ImageStandardParams $image_std_params): string
    {
        return (new DerivativeImage($img, $params, $image_std_params))->getUrl();
    }
}
