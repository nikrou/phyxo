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

namespace App\Utils;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class DirectoryManager
{
    final public const string METHOD_COPY = 'copy';
    final public const string METHOD_ABSOLUTE_SYMLINK = 'absolute symlink';
    final public const string METHOD_RELATIVE_SYMLINK = 'relative symlink';

    public function __construct(
        private readonly Filesystem $fs,
    ) {
    }

    public function relativeSymlinkWithFallback(string $originDir, string $targetDir): string
    {
        try {
            $this->symlink($originDir, $targetDir, true);
            $method = self::METHOD_RELATIVE_SYMLINK;
        } catch (IOException) {
            $method = $this->absoluteSymlinkWithFallback($originDir, $targetDir);
        }

        return $method;
    }

    public function absoluteSymlinkWithFallback(string $originDir, string $targetDir): string
    {
        try {
            $this->symlink($originDir, $targetDir);
            $method = self::METHOD_ABSOLUTE_SYMLINK;
        } catch (IOException) {
            $method = $this->hardCopy($originDir, $targetDir);
        }

        return $method;
    }

    public function symlink(string $originDir, string $targetDir, bool $relative = false): void
    {
        if ($relative) {
            $this->fs->mkdir(dirname($targetDir));
            $originDir = $this->fs->makePathRelative($originDir, realpath(dirname($targetDir)));
        }

        $this->fs->symlink($originDir, $targetDir);
        if (!file_exists($targetDir)) {
            throw new IOException(sprintf('Symbolic link "%s" was created but appears to be broken.', $targetDir), 0, null, $targetDir);
        }
    }

    public function hardCopy(string $originDir, string $targetDir): string
    {
        $this->fs->mkdir($targetDir, 0777);
        $this->fs->mirror($originDir, $targetDir, Finder::create()->ignoreDotFiles(false)->in($originDir));

        return self::METHOD_COPY;
    }
}
