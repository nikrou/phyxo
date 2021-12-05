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

use Imagine\Image\Box;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;

class ImageOptimizer
{
    private $sourceFilePath, $imagine, $image;

    public function __construct(string $sourceFilePath, ImagineInterface $imagine)
    {
        $this->imagine = $imagine;
        $this->sourceFilePath = $sourceFilePath;

        $this->image = $this->imagine->open($this->sourceFilePath);
    }

    public function rotate($rotationAngle): void
    {
        $this->image->rotate($rotationAngle);
    }

    public function getWidth(): int
    {
        return $this->image->getSize()->getWidth();
    }

    public function getHeight(): int
    {
        return $this->image->getSize()->getHeight();
    }

    public function crop(int $width, int $height, int $x, int $y): void
    {
        $this->image->crop(new Point($x, $y), new Box($width, $height));
    }

    public function resize(int $width, int $height): void
    {
        $this->image->resize(new Box($width, $height));
    }

    public function strip(): void
    {
        $this->image->strip();
    }

    public function setCompressionQuality(int $quality): void
    {
        // @TODO: search how to set param
    }

    public static function getRotationAngle(string $sourceFilePath)
    {
        list($width, $height, $type) = getimagesize($sourceFilePath);
        if ($type !== IMAGETYPE_JPEG) {
            return null;
        }

        if (!function_exists('exif_read_data')) {
            return null;
        }

        $rotation = 0;

        $exif = @exif_read_data($sourceFilePath);

        if (isset($exif['Orientation']) and preg_match('/^\s*(\d)/', $exif['Orientation'], $matches)) {
            $orientation = $matches[1];
            if (in_array($orientation, [3, 4])) {
                $rotation = 180;
            } elseif (in_array($orientation, [5, 6])) {
                $rotation = 270;
            } elseif (in_array($orientation, [7, 8])) {
                $rotation = 90;
            }
        }

        return $rotation;
    }

    public static function getRotationCodeFromAngle($rotation_angle)
    {
        switch ($rotation_angle) {
            case 0:
                return 0;
            case 90:
                return 1;
            case 180:
                return 2;
            case 270:
                return 3;
        }
    }

    public static function getRotationAngleFromCode($rotation_code)
    {
        switch ($rotation_code % 4) {
            case 0:
                return 0;
            case 1:
                return 90;
            case 2:
                return 180;
            case 3:
                return 270;
        }
    }

    public function compose($overlay, $x, $y, $opacity): bool
    {
        // why ?

        return false;
    }

    public function sharpen($amount): bool
    {
        // why ?
        $matrix = $this->getSharpenMatrix($amount);

        return false;
    }

    protected function getSharpenMatrix($amount)
    {
        // Amount should be in the range of 48-10
        $amount = round(abs(-48 + ($amount * 0.38)), 2);

        $matrix = [
            [-1, -1, -1],
            [-1, $amount, -1],
            [-1, -1, -1],
        ];

        $norm = array_sum(array_map('array_sum', $matrix));

        for ($i = 0; $i < 3; $i++) {
            $line = $matrix[$i];
            for ($j = 0; $j < 3; $j++) {
                $line[$j] /= $norm;
            }
        }

        return $matrix;
    }

    // resize function
    public function mainResize($destination_filepath, $max_width, $max_height, $quality, $automatic_rotation = true, $strip_metadata = false, $crop = false, $follow_orientation = true)
    {
        $starttime = microtime(true);

        // width/height
        $source_width = $this->image->getSize()->getWidth();
        $source_height = $this->image->getSize()->getHeight();

        $rotation = null;
        if ($automatic_rotation) {
            $rotation = self::getRotationAngle($this->sourceFilePath);
        }
        $resize_dimensions = self::getResizeDimensions($source_width, $source_height, $max_width, $max_height, $rotation, $crop, $follow_orientation);

        // testing on height is useless in theory: if width is unchanged, there
        // should be no resize, because width/height ratio is not modified.
        if ($resize_dimensions['width'] === $source_width && $resize_dimensions['height'] === $source_height) {
            // the image doesn't need any resize! We just copy it to the destination
            copy($this->sourceFilePath, $destination_filepath);
            return $this->getResizeResult($destination_filepath, $resize_dimensions['width'], $resize_dimensions['height'], $starttime);
        }

        $this->setCompressionQuality($quality);

        if ($strip_metadata) {
            // we save a few kilobytes. For example a thumbnail with metadata weights 25KB, without metadata 7KB.
            $this->image->strip();
        }

        if (isset($resize_dimensions['crop'])) {
            $this->image->crop($resize_dimensions['crop']['width'], $resize_dimensions['crop']['height'], $resize_dimensions['crop']['x'], $resize_dimensions['crop']['y']);
        }

        $this->resize($resize_dimensions['width'], $resize_dimensions['height']);

        if (!empty($rotation)) {
            $this->image->rotate($rotation);
        }

        $this->write($destination_filepath);

        // everything should be OK if we are here!
        return $this->getResizeResult($destination_filepath, $resize_dimensions['width'], $resize_dimensions['height'], $starttime);
    }

    public static function getResizeDimensions($width, $height, $max_width, $max_height, $rotation = null, $crop = false, $follow_orientation = true)
    {
        $rotate_for_dimensions = false;
        if (isset($rotation) and in_array(abs($rotation), [90, 270])) {
            $rotate_for_dimensions = true;
        }

        if ($rotate_for_dimensions) {
            list($width, $height) = [$height, $width];
        }

        $x = 0;
        $y = 0;
        if ($crop) {
            if ($width < $height and $follow_orientation) {
                list($max_width, $max_height) = [$max_height, $max_width];
            }

            $img_ratio = $width / $height;
            $dest_ratio = $max_width / $max_height;

            if ($dest_ratio > $img_ratio) {
                $destHeight = round($width * $max_height / $max_width);
                $y = round(($height - $destHeight) / 2);
                $height = $destHeight;
            } elseif ($dest_ratio < $img_ratio) {
                $destWidth = round($height * $max_width / $max_height);
                $x = round(($width - $destWidth) / 2);
                $width = $destWidth;
            }
        }

        $ratio_width = $width / $max_width;
        $ratio_height = $height / $max_height;
        $destination_width = $width;
        $destination_height = $height;

        // maximal size exceeded ?
        if ($ratio_width > 1 or $ratio_height > 1) {
            if ($ratio_width < $ratio_height) {
                $destination_width = round($width / $ratio_height);
                $destination_height = $max_height;
            } else {
                $destination_width = $max_width;
                $destination_height = round($height / $ratio_width);
            }
        }

        if ($rotate_for_dimensions) {
            list($destination_width, $destination_height) = [$destination_height, $destination_width];
        }

        $result = [
            'width' => $destination_width,
            'height' => $destination_height,
        ];

        if ($crop && ($x || $y)) {
            $result['crop'] = [
                'width' => $width,
                'height' => $height,
                'x' => $x,
                'y' => $y,
            ];
        }

        return $result;
    }

    private function getResizeResult($destination_filepath, $width, $height, $time = null): array
    {
        return [
            'source' => $this->sourceFilePath,
            'destination' => $destination_filepath,
            'width' => $width,
            'height' => $height,
            'size' => floor(filesize($destination_filepath) / 1024) . ' KB',
            'time' => $time ? number_format((microtime(true) - $time) * 1000, 2, '.', ' ') . ' ms' : null,
            'library' => get_class($this->imagine),
        ];
    }

    public function write(string $imagePath): void
    {
        $this->image->save($imagePath);
    }

    public function destroy()
    {
        // @TODO: free image resource
        // imagedestroy($this->image);
    }
}
