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
use App\Enum\ImageSizeType;

/**
 * Holds information (path, url, dimensions) about a derivative image.
 * A derivative image is constructed from a source image (Image class)
 * and derivative parameters (DerivativeParams class).
 */
class DerivativeImage
{
    public function __construct(private readonly Image $image, private DerivativeParams $params, private readonly ImageStandardParams $image_std_params)
    {
    }

    public function getExtension(): string
    {
        return $this->image->getExtension();
    }

    public function getPathBasename(): string
    {
        return $this->image->getPathBasename();
    }

    public function getType(): ImageSizeType
    {
        return $this->params->type;
    }

    public function getUrlType(): string
    {
        return DerivativeParams::derivativeToUrl($this->params->type->value);
    }

    /**
     * @return array{0:int, 1:int}|array{}
     */
    public function getSize(): array
    {
        if (!$this->image->getWidth() || !$this->image->getHeight()) {
            return [];
        }

        $width = $this->image->getWidth();
        $height = $this->image->getHeight();

        $rotation = intval($this->image->getRotation()) % 4;
        // 1 or 5 =>  90 clockwise
        // 3 or 7 => 270 clockwise
        if ($rotation % 2 !== 0) {
            $width = $this->image->getHeight();
            $height = $this->image->getWidth();
        }

        if ($this->params == null) {
            return [$width, $height];
        }

        return $this->params->computeFinalSize([$width, $height]);
    }

    public function getUrlSize(): string
    {
        $tokens = [];

        if ($this->params->type === ImageSizeType::CUSTOM) {
            $this->params->addUrlTokens($tokens);
        }

        return implode('', $tokens);
    }

    /**
     * Returns literal size: $widthx$height.
     */
    public function getLiteralSize(): string
    {
        $size = $this->getSize();
        if ($size === []) {
            return '';
        }

        return $size[0] . ' x ' . $size[1];
    }

    /**
     * Generates the url of a thumbnail.
     *
     * @return array{path: string, derivative: string, sizes: string, image_extension: string}
     */
    public function relativeThumbInfos(): array
    {
        return $this->buildInfos();
    }

    /**
     * @return array{path: string, derivative: string, sizes: string, image_extension: string}
     */
    private function buildInfos(): array
    {
        if ($this->getSize() !== [] && $this->params->isIdentity($this->getSize())) {
            // the source image is smaller than what we should do - we do not upsample
            if (!$this->params->willWatermark($this->getSize(), $this->image_std_params) && !$this->image->getRotation()) {
                // no watermark, no rotation required -> we will use the source image
                return [
                    'path' => $this->getPathBasename(),
                    'derivative' => substr((string) $this->params->type->value, 0, 2),
                    'sizes' => '',
                    'image_extension' => $this->getExtension()
                ];
            }

            $defined_types = array_values($this->image_std_params->getDefinedTypeMap());
            $counter = count($defined_types);
            for ($i = 0; $i < $counter; $i++) {
                if ($defined_types[$i]->type === $this->params->type) {
                    for ($i--; $i >= 0; $i--) {
                        $smaller = $this->image_std_params->getByType($defined_types[$i]->type);
                        if ($smaller->getSizing()->getMaxCrop() === $this->params->getSizing()->getMaxCrop() && $smaller->isIdentity($this->getSize())) {
                            $this->params = $smaller;
                            return [
                                'path' => $this->getPathBasename(),
                                'derivative' => substr($this->params->type->value, 0, 2),
                                'sizes' => '',
                                'image_extension' => $this->getExtension()
                            ];
                        }
                    }
                    break;
                }
            }
        }

        $tokens = [];
        $tokens[] = substr((string) $this->params->type->value, 0, 2);

        if ($this->params->type === ImageSizeType::CUSTOM) {
            $this->params->addUrlTokens($tokens);
        }

        return [
            'path' => $this->getPathBasename(),
            'derivative' => substr((string) $this->params->type->value, 0, 2),
            'sizes' => '',
            'image_extension' => $this->getExtension()
        ];
    }

    public function sameAsSource(): bool
    {
        return $this->params == null;
    }

    public function isCached(): bool
    {
        return true;
    }
}
