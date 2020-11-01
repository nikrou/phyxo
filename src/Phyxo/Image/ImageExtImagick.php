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

use Phyxo\Image\ImageInterface;

class ImageExtImagick implements ImageInterface
{
    private $imagickdir = '', $source_filepath = '',
        $width = '', $height = '', $commands = [];

    public function __construct($source_filepath)
    {
        global $conf;
        $this->source_filepath = $source_filepath;
        $this->imagickdir = $conf['ext_imagick_dir'];

        if (strpos(@$_SERVER['SCRIPT_FILENAME'], '/kunden/') === 0) { // 1and1 ???
            @putenv('MAGICK_THREAD_LIMIT=1');
        }

        $command = $this->imagickdir . 'identify -format "%wx%h" "' . realpath($source_filepath) . '"';
        @exec($command, $returnarray);
        if (!is_array($returnarray) or empty($returnarray[0]) or !preg_match('/^(\d+)x(\d+)$/', $returnarray[0], $match)) {
            die("[External ImageMagick] Corrupt image\n" . var_export($returnarray, true));
        }

        $this->width = $match[1];
        $this->height = $match[2];
    }

    public function add_command($command, $params = null)
    {
        $this->commands[$command] = $params;
    }

    public function get_width()
    {
        return $this->width;
    }

    public function get_height()
    {
        return $this->height;
    }

    public function crop($width, $height, $x, $y)
    {
        $this->width = $width;
        $this->height = $height;

        $this->add_command('crop', $width . 'x' . $height . '+' . $x . '+' . $y);
    }

    public function strip()
    {
        $this->add_command('strip');
    }

    public function rotate($rotation)
    {
        if (empty($rotation)) {
            return;
        }

        if ($rotation == 90 || $rotation == 270) {
            $tmp = $this->width;
            $this->width = $this->height;
            $this->height = $tmp;
        }
        $this->add_command('rotate', -$rotation);
        $this->add_command('orient', 'top-left');
    }

    public function set_compression_quality($quality)
    {
        $this->add_command('quality', $quality);
    }

    public function resize($width, $height)
    {
        $this->width = $width;
        $this->height = $height;

        $this->add_command('filter', 'Lanczos');
        $this->add_command('resize', $width . 'x' . $height . '!');
    }

    public function sharpen($amount)
    {
        $m = \Phyxo\Image\Image::getSharpenMatrix($amount);

        $param = 'convolve "' . count($m) . ':';
        foreach ($m as $line) {
            $param .= ' ';
            $param .= implode(',', $line);
        }
        $param .= '"';
        $this->add_command('morphology', $param);
    }

    public function compose($overlay, $x, $y, $opacity)
    {
        $param = 'compose dissolve -define compose:args=' . $opacity;
        $param .= ' ' . escapeshellarg(realpath($overlay->image->source_filepath));
        $param .= ' -gravity NorthWest -geometry +' . $x . '+' . $y;
        $param .= ' -composite';
        $this->add_command($param);
    }

    public function write($destination_filepath)
    {
        $this->add_command('interlace', 'line'); // progressive rendering
        // use 4:2:2 chroma subsampling (reduce file size by 20-30% with "almost" no human perception)
        //
        // option deactivated for Piwigo 2.4.1, it doesn't work fo old versions
        // of ImageMagick, see bug:2672. To reactivate once we have a better way
        // to detect IM version and when we know which version supports this
        // option
        //
        if (version_compare(\Phyxo\Image\Image::$ext_imagick_version, '6.6') > 0) {
            $this->add_command('sampling-factor', '4:2:2');
        }

        $exec = $this->imagickdir . 'convert';
        $exec .= ' "' . realpath($this->source_filepath) . '"';

        foreach ($this->commands as $command => $params) {
            $exec .= ' -' . $command;
            if (!empty($params)) {
                $exec .= ' ' . $params;
            }
        }

        $dest = pathinfo($destination_filepath);
        $exec .= ' "' . realpath($dest['dirname']) . '/' . $dest['basename'] . '" 2>&1';
        @exec($exec, $returnarray);

        if (is_array($returnarray) && (count($returnarray) > 0)) {
            foreach ($returnarray as $line) {
                trigger_error($line, E_USER_WARNING);
            }
        }
        return is_array($returnarray);
    }
}
