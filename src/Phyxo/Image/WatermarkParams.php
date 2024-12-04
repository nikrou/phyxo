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

/**
 * Container for watermark configuration.
 */
class WatermarkParams
{
    /** @var string */
    public $file = '';

    /** @var int[] */
    public $min_size = [500, 500];

    /** @var int */
    public $xpos = 50;

    /** @var int */
    public $ypos = 50;

    /** @var int */
    public $xrepeat = 0;

    /** @var int */
    public $opacity = 100;
}
