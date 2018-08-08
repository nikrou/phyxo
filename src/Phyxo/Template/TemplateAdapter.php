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

class TemplateAdapter
{
    /**
     * @param string $type
     * @param array $img
     * @return DerivativeImage
     */
    public function derivative($type, $img)
    {
        return new DerivativeImage($type, $img);
    }

    /**
     * @param string $type
     * @param array $img
     * @return string
     */
    public function derivative_url($type, $img)
    {
        return DerivativeImage::url($type, $img);
    }
}
