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

namespace App;

use Imagine\Gd\Imagine as GdImagine;
use Imagine\Imagick\Imagine as ImagickImagine;
use Imagine\Gmagick\Imagine as GmagickImagine;
use Imagine\Image\ImagineInterface;

class ImageLibraryGuesser
{
    private ImagineInterface $library;

    public function __construct(string $graphicsLibrary)
    {
        if ($graphicsLibrary === 'auto') {
            $this->library = $this->guessLibrary();
        } elseif ($graphicsLibrary === 'gmagick') {
            $this->library = new GmagickImagine();
        } elseif ($graphicsLibrary === 'imagick') {
            $this->library = new ImagickImagine();
        } elseif ($graphicsLibrary === 'gd') {
            $this->library = new GdImagine();
        }
    }

    public function guessLibrary(): ImagineInterface
    {
        if (class_exists('Gmagick')) {
            return new GmagickImagine();
        } elseif (class_exists('Imagick')) {
            return new ImagickImagine();
        } else {
            return new GdImagine();
        }
    }

    public function getLibrary(): ImagineInterface
    {
        return $this->library;
    }
}
