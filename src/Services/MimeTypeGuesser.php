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

use finfo;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

class MimeTypeGuesser implements MimeTypeGuesserInterface
{
    public function isGuesserSupported(): bool
    {
        return \function_exists('finfo_open');
    }

    public function guessMimeType(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        switch ($extension) {
            case 'css':
                $mime_type = 'text/css';
                break;
            case 'js':
                $mime_type = 'application/javascript';
                break;
            default:
                $finfo = new finfo(\FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($path);
                if (!$mime_type) {
                    $mime_type = 'text/plain';
                }
        }

        return $mime_type;
    }
}
