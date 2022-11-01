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
use Imagine\Image\Metadata\ExifMetadataReader;
use Imagine\Image\Point;

class ImageOptimizer
{
    private ImageInterface $image;

    public function __construct(private string $sourceFilePath, private ImagineInterface $imagine)
    {
        $this->imagine->setMetadataReader(new ExifMetadataReader());

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

    public function getImage(): ImageInterface
    {
        return $this->image;
    }

    public function compose(ImageInterface $overlay, float $x, float $y, int $opacity): bool
    {
        return false;
    }

    public function addWatermak(WatermarkParams $watermark): void
    {
        // $wm = $image_std_params->getWatermark();
        // $wm_image = new ImageOptimizer(__DIR__ . '/../../' . $wm->file, $imageLibraryGuesser->getLibrary());
        // $wm_size = [$wm_image->getWidth(), $wm_image->getHeight()];
        // if ($d_size[0] < $wm_size[0] or $d_size[1] < $wm_size[1]) {
        //     $wm_scaling_params = SizingParams::classic($d_size[0], $d_size[1]);
        //     $wm_scaling_params->compute($wm_size, '', $tmp, $wm_scaled_size);
        //     $wm_size = $wm_scaled_size;
        //     $wm_image->resize($wm_scaled_size[0], $wm_scaled_size[1]);
        // }
        // $x = round(($wm->xpos / 100) * ($d_size[0] - $wm_size[0]));
        // $y = round(($wm->ypos / 100) * ($d_size[1] - $wm_size[1]));

        // if ($imageOptimizer->compose($wm_image->getImage(), $x, $y, $wm->opacity)) {
        //     if ($wm->xrepeat) {
        //         // todo
        //         $pad = $wm_size[0] + max(30, round($wm_size[0] / 4));
        //         for ($i = -$wm->xrepeat; $i <= $wm->xrepeat; $i++) {
        //             if (!$i) {
        //                 continue;
        //             }
        //             $x2 = $x + $i * $pad;
        //             if ($x2 >= 0 && $x2 + $wm_size[0] < $d_size[0]) {
        //                 if (!$imageOptimizer->compose($wm_image->getImage(), $x2, $y, $wm->opacity)) {
        //                     break;
        //                 }
        //             }
        //         }
        //     }
        // }
        // $wm_image->destroy();
    }

    public function getRotationCode(): int
    {
        $metadata = $this->image->metadata();
        $orientation = $metadata['ifd0.Orientation'] ?? null;

        if (is_null($orientation)) {
            return 0;
        }

        return $orientation;
    }

    public function getRotationAngle(): int
    {
        $orientationCode = $this->getRotationCode();
        if (in_array($orientationCode, [3, 4])) {
            $rotation = 180;
        } elseif (in_array($orientationCode, [5, 6])) {
            $rotation = 90;
        } elseif (in_array($orientationCode, [7, 8])) {
            $rotation = 270;
        } else {
            $rotation = 0;
        }

        return $rotation;
    }

    /**
     * @return array{source: string, destination: string, width: int, height: int, size: string, time: string|null, library: string}
     */
    public function mainResize(
        string $destinationFilePath,
        int $maxWidth,
        int $maxHeight,
        int $quality,
        bool $automaticRotation = true,
        bool $stripMetadata = false,
        bool $crop = false,
        bool $followOrientation = true
    ) {
        $starttime = microtime(true);

        $sourceWidth = $this->image->getSize()->getWidth();
        $sourceHeight = $this->image->getSize()->getHeight();

        $rotation = null;
        if ($automaticRotation) {
            $rotation = $this->getRotationAngle();
        }

        $resizeDimensions = $this->getResizeDimensions($sourceWidth, $sourceHeight, $maxWidth, $maxHeight, $rotation, $crop, $followOrientation);

        // testing on height is useless in theory: if width is unchanged, there should be no resize, because width/height ratio is not modified.
        if ($resizeDimensions['width'] === $sourceWidth && $resizeDimensions['height'] === $sourceHeight) {
            // the image doesn't need any resize! We just copy it to the destination
            copy($this->sourceFilePath, $destinationFilePath);

            return $this->getResizeResult($destinationFilePath, $resizeDimensions['width'], $resizeDimensions['height'], $starttime);
        }

        $this->setCompressionQuality($quality);

        if ($stripMetadata) {
            $this->strip();
        }

        if (isset($resizeDimensions['crop'])) {
            $this->crop($resizeDimensions['crop']['width'], $resizeDimensions['crop']['height'], $resizeDimensions['crop']['x'], $resizeDimensions['crop']['y']);
        }

        $this->resize($resizeDimensions['width'], $resizeDimensions['height']);

        if (!empty($rotation)) {
            $this->rotate($rotation);
        }

        $this->write($destinationFilePath);

        return $this->getResizeResult($destinationFilePath, $resizeDimensions['width'], $resizeDimensions['height'], $starttime);
    }

    public function autoRotate(): void
    {
        $filter = new Autorotate();
        $filter->apply($this->image);
    }

    /**
     * @return array{width: int, height: int, crop?: array{width: int, height: int, x: int, y: int}}
     */
    public function getResizeDimensions(int $width, int $height, int $maxWidth, int $maxHeight, int $rotation = null, bool $crop = false, bool $followOrientation = true): array
    {
        $rotateForDimensions = false;
        if (!is_null($rotation) && in_array(abs($rotation), [90, 270])) {
            $rotateForDimensions = true;
        }

        if ($rotateForDimensions) {
            [$width, $height] = [$height, $width];
        }

        $x = 0;
        $y = 0;
        if ($crop) {
            if ($width < $height && $followOrientation) {
                [$maxWidth, $maxHeight] = [$maxHeight, $maxWidth];
            }

            $imageRatio = $width / $height;
            $destinationRatio = $maxWidth / $maxHeight;

            if ($destinationRatio > $imageRatio) {
                $destinationHeight = round($width * $maxHeight / $maxWidth);
                $y = round(($height - $destinationHeight) / 2);
                $height = $destinationHeight;
            } elseif ($destinationRatio < $imageRatio) {
                $destinationWidth = round($height * $maxWidth / $maxHeight);
                $x = round(($width - $destinationWidth) / 2);
                $width = $destinationWidth;
            }
        }

        $ratioWidth = $width / $maxWidth;
        $ratioHeight = $height / $maxHeight;
        $destinationWidth = $width;
        $destinationHeight = $height;

        // maximal size exceeded ?
        if ($ratioWidth > 1 || $ratioHeight > 1) {
            if ($ratioWidth < $ratioHeight) {
                $destinationWidth = round($width / $ratioHeight);
                $destinationHeight = $maxHeight;
            } else {
                $destinationWidth = $maxWidth;
                $destinationHeight = round($height / $ratioWidth);
            }
        }

        if ($rotateForDimensions) {
            [$destinationWidth, $destinationHeight] = [$destinationHeight, $destinationWidth];
        }

        $result = [
            'width' => $destinationWidth,
            'height' => $destinationHeight,
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
    private function getResizeResult(string $destinationFilePath, int $width, int $height, float $time = null)
    {
        return [
            'source' => $this->sourceFilePath,
            'destination' => $destinationFilePath,
            'width' => $width,
            'height' => $height,
            'size' => floor(filesize($destinationFilePath) / 1024) . ' KB',
            'time' => $time ? number_format((microtime(true) - $time) * 1000, 2, '.', ' ') . ' ms' : null,
            'library' => $this->imagine::class,
        ];
    }

    public function write(string $imagePath): void
    {
        $this->image->save($imagePath);
    }

    public function destroy(): void
    {
        // done by ImageInterface::__destruct()
    }
}
