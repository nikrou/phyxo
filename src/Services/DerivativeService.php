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

namespace App\Services;

use App\Enum\ImageSizeType;
use Phyxo\Image\DerivativeParams;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class DerivativeService
{
    public function __construct(private readonly string $mediaCacheDir, private readonly string $rootProjectDir, private readonly string $uploadDir)
    {
    }

    /**
     * Delete all derivative files for one or several types
     *
     * @param ImageSizeType[] $types
     * @param ImageSizeType[] $all_types
     */
    public function clearCache(array $types, array $all_types): void
    {
        $counter = count($types);
        for ($i = 0; $i < $counter; $i++) {
            $type = $types[$i];
            if ($type === ImageSizeType::CUSTOM) {
                $pattern = DerivativeParams::derivativeToUrl($type->value) . '[a-zA-Z0-9]+';
            } elseif (in_array($type, $all_types)) {
                $pattern = DerivativeParams::derivativeToUrl($type->value);
            } else { //assume a custom type
                $pattern = DerivativeParams::derivativeToUrl(ImageSizeType::CUSTOM->value) . '_' . $type;
            }
            $types[$i] = $pattern;
        }

        $pattern = '#.*-';
        if (count($types) > 1) {
            $pattern .= '(' . implode('|', $types) . ')';
        } else {
            $pattern .= $types[0];
        }
        $pattern .= '\.[a-zA-Z0-9]{3,4}$#';

        $fs = new Filesystem();
        $finder = new Finder();
        $finder->files()->in($this->mediaCacheDir)->name($pattern);
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $fs->remove($file->getPathname());
            }
        }
    }

    /**
     * Deletes derivatives of a particular element
     */
    public function deleteForElement(string $path, string $type = 'all'): void
    {
        $fs = new Filesystem();

        $relative_path = $fs->makePathRelative(sprintf('%s/%s', $this->rootProjectDir, $path), $this->uploadDir);
        $relative_path = sprintf('%s/%s', basename($this->uploadDir), $relative_path);
        $relative_path = rtrim($relative_path, '/');

        $dot = strrpos($relative_path, '.');
        $pattern = $type === 'all' ? '-.*' : '-' . DerivativeParams::derivativeToUrl($type) . '.*';
        $pattern = '#' . substr_replace($relative_path, $pattern, $dot, 0) . '#';

        $finder = new Finder();
        $finder->files()->in($this->mediaCacheDir)->path($pattern);
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $fs->remove($file->getPathname());
            }
        }
    }
}
