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

use Imagine\Filter\Basic\Autorotate;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Metadata\DefaultMetadataReader;
use Imagine\Image\Metadata\MetadataBag;
use Imagine\Image\Point;

class ImageOptimizer
{
    private string $sourceFilePath;
    private ImagineInterface $imagine;
    private ImageInterface $image;

    public function __construct(string $sourceFilePath, ImagineInterface $imagine)
    {
        $this->imagine = $imagine;
        $this->imagine->setMetadataReader(new DefaultMetadataReader());

        $this->sourceFilePath = $sourceFilePath;

        $this->image = $this->imagine->open($this->sourceFilePath);
    }

    public function rotate(int $rotationAngle): void
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

    public function getRotationAngle(): int
    {
        $metadata = $this->image->metadata();
        $orientation = $metadata->get('exif.Orientation');

        if (is_null($orientation)) {
            return 0;
        }

        return $orientation;
    }

    public function getRotationCodeFromAngle(): int
    {
        switch ($this->getRotationAngle()) {
            case 0:
                return 0;
            case 90:
                return 1;
            case 180:
                return 2;
            case 270:
                return 3;
            default:
                return 0;
        }
    }

    public function getRotationAngleFromCode(int $rotation_code): int
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
                default:
                return 0;
        }
    }

    /**
     * @return array{source: string, destination: string, width: int, height: int, size: string, time: string|null, library: string}
     */
    public function mainResize(
        string $destination_filepath,
        int $max_width,
        int $max_height,
        int $quality,
        bool $automatic_rotation = true,
        bool $strip_metadata = false,
        bool $crop = false,
        bool $follow_orientation = true
    ) {
        $starttime = microtime(true);

        // width/height
        $source_width = $this->image->getSize()->getWidth();
        $source_height = $this->image->getSize()->getHeight();

        $rotation = null;
        if ($automatic_rotation) {
            $rotation = $this->getRotationAngle();
            $this->AutoRotate();
        }
        $resize_dimensions = $this->getResizeDimensions($source_width, $source_height, $max_width, $max_height, $rotation, $crop, $follow_orientation);

        // testing on height is useless in theory: if width is unchanged, there should be no resize, because width/height ratio is not modified.
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
            $this->crop($resize_dimensions['crop']['width'], $resize_dimensions['crop']['height'], $resize_dimensions['crop']['x'], $resize_dimensions['crop']['y']);
        }

        $this->resize($resize_dimensions['width'], $resize_dimensions['height']);

        if ($automatic_rotation) {
            $this->AutoRotate();
        }

        $this->write($destination_filepath);

        return $this->getResizeResult($destination_filepath, $resize_dimensions['width'], $resize_dimensions['height'], $starttime);
    }

    public function AutoRotate(): void
    {
        $filter = new Autorotate();
        $filter->apply($this->image);
    }

    public function getResizeDimensions(int $width, int $height, int $max_width, int $max_height, int $rotation = null, bool $crop = false, bool $follow_orientation = true): mixed
    {
        $rotate_for_dimensions = false;
        if (!is_null($rotation) && in_array(abs($rotation), [90, 270])) {
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

    /**
     * @return array{source: string, destination: string, width: int, height: int, size: string, time: string|null, library: string}
     */
    private function getResizeResult(string $destination_filepath, int $width, int $height, float $time = null)
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

    public function destroy(): void
    {
        // @TODO: free image resource
        // imagedestroy($this->image);
    }
}
