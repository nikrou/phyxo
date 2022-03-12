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

use App\Entity\Image;
use App\Entity\User;
use App\Repository\ImageRepository;
use App\Security\UserProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

class MediaControllerTest extends WebTestCase
{
    use ProphecyTrait;

    private $fixtures_dir = __DIR__ . '/../fixtures/media', $sample_image = 'sample.jpg', $image_paths = '', $derivative_path = '';
    private $imageRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $uploadDir = $kernel->getContainer()->getParameter('upload_dir');
        $mediaCacheDir = $kernel->getContainer()->getParameter('media_cache_dir');

        $now = new \DateTime('now');
        $base_image_path = sprintf('tests/upload/%s/%s-%s', $now->format('Y/m/d'), $now->format('YmdHis'), substr(md5($this->sample_image), 0, 8));
        $image_upload_path = sprintf('%s/%s.jpg', $kernel->getContainer()->getParameter('root_project_dir'), $base_image_path);
        $this->image_paths = [
            'path' => sprintf('%s.jpg', $base_image_path),
            'sq' => sprintf('%s-sq.jpg', $base_image_path),
            'unknown' => sprintf('%s-cu_e777x333.jpg', $base_image_path),
            'custom' => sprintf('%s-cu_e1200x900.jpg', $base_image_path),
        ];
        $this->derivative_path = sprintf('%s/%s-sq.jpg', $mediaCacheDir, $base_image_path);

        $image = new Image();
        $image->fromArray(['id' => 1, 'path' => $this->image_paths['path'], 'width' => 961, 'height' => 1200]);

        $this->imageRepository = $this->prophesize(ImageRepository::class);
        $this->imageRepository->findOneByUnsanePath(Argument::any())->willReturn($image);
        $this->imageRepository->addOrUpdateImage(Argument::any())->willReturn(1);
        $this->imageRepository->getForbiddenImages(Argument::any(), Argument::any())->willReturn([]);
        $this->imageRepository->isAuthorizedToUser(Argument::any(), Argument::any())->willReturn(true);

        $fs = new Filesystem();
        $fs->remove($mediaCacheDir);
        $fs->remove($uploadDir);
        $fs->mkdir(dirname($image_upload_path));
        $fs->copy(sprintf('%s/%s', $this->fixtures_dir, $this->sample_image), $image_upload_path);
    }

    public function testUnkwnonImageGive404Error()
    {
        self::ensureKernelShutdown();

        $client = static::createClient();
        $client->request('GET', '/media/tests/upload/dummy-sq.jpg');

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }

    public function testGenerateImage()
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();
        $container->set('App\Repository\ImageRepository', $this->imageRepository->reveal());
        $client->request('GET', sprintf('/media/%s', $this->image_paths['sq']));

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testReusedGenerateImage()
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();
        $container->set('App\Repository\ImageRepository', $this->imageRepository->reveal());
        $client->request('GET', sprintf('/media/%s', $this->image_paths['sq']));

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertTrue(
            $client->getResponse()->headers->contains(
                'Content-Type',
                'image/jpeg'
            ),
            'the "Content-Type" header is "image/jpeg"'
        );

        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();
        $container->set('App\Repository\ImageRepository', $this->imageRepository->reveal());

        // same path, image not changed so http status code must be 304
        $client->request(
            'GET',
            sprintf('/media/%s', $this->image_paths['sq']),
            [],
            [],
            [
                'HTTP_If-Modified-Since' => gmdate('D, d M Y H:i:s', filemtime($this->derivative_path)) . ' GMT',
                'HTTP_If-None-Match' => '"' . md5_file($this->derivative_path) . '"',
                'HTTP_Etag' => '"' . md5_file($this->derivative_path) . '"'
            ]
        );

        $this->assertEquals(304, $client->getResponse()->getStatusCode());
    }

    public function testGenerateUnknownCustomSizeNotAllowed()
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();
        $container->set('App\Repository\ImageRepository', $this->imageRepository->reveal());
        $client->request('GET', sprintf('/media/%s', $this->image_paths['unknown']));

        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testGenerateCustomImage()
    {
        $width = 1200;
        $height = 900;
        $custom = sprintf('e%dx%d', $width, $height);

        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();
        $container->set('App\Repository\ImageRepository', $this->imageRepository->reveal());

        $image_std_params = $container->get('Phyxo\Image\ImageStandardParams');
        $image_std_params->makeCustom($width, $height, 1, $width, $height);
        $this->assertTrue($image_std_params->hasCustom($custom));

        $client->request('GET', sprintf('/media/%s', $this->image_paths['custom']));
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testAdminMedia()
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();
        $container->set('App\Repository\ImageRepository', $this->imageRepository->reveal());
        $client->request('GET', sprintf('/admin/media/%s', $this->image_paths['sq']));
        $client->followRedirect();

        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testAdminMediaFromAdmin()
    {
        /** @var MockObject $admin */
        $admin = $this->createStub(User::class);
        $admin->method('getRoles')->willReturn(['ROLE_WEBMASTER']);

        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();

        $userProvider = $this->prophesize(UserProvider::class);
        $userProvider->supportsClass(Argument::any())->willReturn(true);
        $userProvider->refreshUser(Argument::any())->willReturn($admin);
        $container->set('App\Security\UserProvider', $userProvider->reveal());

        $client->loginUser($admin);

        $container = static::getContainer();
        $container->set('App\Repository\ImageRepository', $this->imageRepository->reveal());
        $client->request('GET', sprintf('/admin/media/%s', $this->image_paths['sq']));

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }
}
