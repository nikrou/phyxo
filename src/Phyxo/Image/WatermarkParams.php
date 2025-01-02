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
    private string $file = '';

    /** @var array{0:int, 1:int} */
    private $min_size = [500, 500];
    private int $xpos = 50;
    private int $ypos = 50;
    private int $xrepeat = 0;
    private int $opacity = 100;

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): self
    {
        $this->file = $file;

        return $this;
    }

    /**
     * @return array{0:int, 1:int}
     */
    public function getMinSize(): array
    {
        return $this->min_size;
    }

    /**
     * @param array{0:int, 1:int} $min_size
     */
    public function setMinSize(array $min_size): self
    {
        $this->min_size = $min_size;

        return $this;
    }

    public function getXpos(): int
    {
        return $this->xpos;
    }

    public function setXpos(int $xpos): self
    {
        $this->xpos = $xpos;

        return $this;
    }

    public function getYpos(): int
    {
        return $this->ypos;
    }

    public function setYpos(int $ypos): self
    {
        $this->ypos = $ypos;

        return $this;
    }

    public function getXrepeat(): int
    {
        return $this->xrepeat;
    }

    public function setXrepeat(int $xrepeat): self
    {
        $this->xrepeat = $xrepeat;

        return $this;
    }

    public function getOpacity(): int
    {
        return $this->opacity;
    }

    public function setOpacity(int $opacity): self
    {
        $this->opacity = $opacity;

        return $this;
    }
}
