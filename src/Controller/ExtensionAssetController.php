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

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

class ExtensionAssetController extends AbstractController
{
    private MimeTypeGuesserInterface $mimeTypes;

    public function __construct(MimeTypeGuesserInterface $mimeTypes)
    {
        $this->mimeTypes = $mimeTypes;
    }

    public function pluginAsset(string $id, string $path, string $pluginsDir): Response
    {
        return $this->sendFile(sprintf('%s/%s/%s', $pluginsDir, $id, $path));
    }

    public function themeAsset(string $id, string $path, string $themesDir): Response
    {
        return $this->sendFile(sprintf('%s/%s/%s', $themesDir, $id, $path));
    }

    protected function sendFile(string $path): Response
    {
        if (!is_readable($path)) {
            throw new NotFoundHttpException(sprintf('Asset "%s" not found"', $path));
        }

        $response = new StreamedResponse();
        $response->setCallback(function() use ($path) {
            readfile($path);
        });
        $response->setEtag(md5_file($path));
        $response->setLastModified((new \DateTime())->setTimestamp(filemtime($path)));
        $response->setMaxAge(3600); //@TODO : read from conf
        $response->setPublic();
        $response->headers->set('Content-Type', $this->mimeTypes->guessMimeType($path));
        $response->send();

        return $response;
    }
}
