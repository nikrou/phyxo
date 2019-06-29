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

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MediaContollerTest extends WebTestCase
{
    public function testGenerateImage()
    {
        $mediaCacheDir = __DIR__ . '/../../_data/i';
        $image_path = 'upload/2019/03/18/2019031818551945391201-e29966c3-sq.jpg';

        $client = static::createClient();
        $client->request(
            'GET',
            "/media/$image_path"
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertTrue(
            $client->getResponse()->headers->contains(
                'Content-Type',
                'image/jpeg'
            ),
            'the "Content-Type" header is "image/jpeg"'
        );

        // same path, image not changed so http status code must be 304
        $client->request(
            'GET',
            "/media/$image_path",
            [],
            [],
            [
                'HTTP_If-Modified-Since' => gmdate('D, d M Y H:i:s', filemtime($mediaCacheDir . '/' . $image_path)) . ' GMT',
                'HTTP_If-None-Match' => '"' . md5_file($mediaCacheDir . '/' . $image_path) . '"',
                'HTTP_Etag' => '"' . md5_file($mediaCacheDir . '/' . $image_path) . '"'
            ]
        );
        $this->assertEquals(304, $client->getResponse()->getStatusCode());
    }

    public function testGenerateCustomSizeNotAllowed()
    {
        $image_path = 'upload/2019/03/18/2019031818551945391201-e29966c3-cu_e777x333.jpg';

        $client = static::createClient();
        $client->request(
            'GET',
            "/media/$image_path"
        );

        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testGenerateCustomImage()
    {
        $width = 1200;
        $height = 900;
        $custom = sprintf('e%dx%d', $width, $height);
        $image_path = sprintf('upload/2019/03/18/2019031818551945391201-e29966c3-cu_%s.jpg', $custom);

        $client = static::createClient();
        $image_std_params = self::$container->get('Phyxo\Image\ImageStandardParams');
        $image_std_params->unsetCustom($custom);
        $this->assertFalse($image_std_params->hasCustom($custom));

        $client->request(
            'GET',
            "/media/$image_path"
        );
        $this->assertEquals(403, $client->getResponse()->getStatusCode());

        // but 200 when size is defined
        $image_std_params->makeCustom($width, $height, 1, $width, $height);
        $this->assertTrue($image_std_params->hasCustom($custom));
        $client->request(
            'GET',
            "/media/$image_path"
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }
}
