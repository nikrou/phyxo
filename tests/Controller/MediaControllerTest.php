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

use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\HttpFoundation\Response;
use Phyxo\Image\ImageStandardParams;
use App\Entity\Image;
use App\Entity\User;
use App\Repository\ImageRepository;
use App\Security\UserProvider;
use App\Tests\Factory\UserFactory;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Zenstruck\Foundry\Test\Factories;

class MediaControllerTest extends WebTestCase
{
    use ProphecyTrait;
    use Factories;
    private string $fixtures_dir = __DIR__ . '/../fixtures/media';
    private string $sample_image = 'sample.jpg';
    private array $image_paths = [];
    private string $derivative_path = '';
    private ObjectProphecy $imageRepository;

    protected function setUp(): void
    {
        UserFactory::findOrCreate(['username' => 'guest']);

        $kernel = self::bootKernel();
        $uploadDir = $kernel->getContainer()->getParameter('upload_dir');
        $mediaCacheDir = $kernel->getContainer()->getParameter('media_cache_dir');

        $now = new DateTime('now');
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

    public function testUnkwnonImageGive404Error(): void
    {
        self::ensureKernelShutdown();

        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/media/tests/upload/dummy-sq.jpg');

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testGenerateImage(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        $client->request(Request::METHOD_GET, sprintf('/media/%s', $this->image_paths['sq']));

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testReusedGenerateImage(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        $client->request(Request::METHOD_GET, sprintf('/media/%s', $this->image_paths['sq']));

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
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
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        // same path, image not changed so http status code must be 304
        $client->request(
            Request::METHOD_GET,
            sprintf('/media/%s', $this->image_paths['sq']),
            [],
            [],
            [
                'HTTP_If-Modified-Since' => gmdate('D, d M Y H:i:s', filemtime($this->derivative_path)) . ' GMT',
                'HTTP_If-None-Match' => '"' . md5_file($this->derivative_path) . '"',
                'HTTP_Etag' => '"' . md5_file($this->derivative_path) . '"'
            ]
        );

        $this->assertEquals(Response::HTTP_NOT_MODIFIED, $client->getResponse()->getStatusCode());
    }

    public function testGenerateUnknownCustomSizeNotAllowed(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        $client->request(Request::METHOD_GET, sprintf('/media/%s', $this->image_paths['unknown']));

        $this->assertEquals(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testGenerateCustomImage(): void
    {
        $width = 1200;
        $height = 900;
        $custom = sprintf('e%dx%d', $width, $height);

        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        $image_std_params = $container->get(ImageStandardParams::class);
        $image_std_params->makeCustom($width, $height, 1, $width, $height);
        $this->assertTrue($image_std_params->hasCustom($custom));

        $client->request(Request::METHOD_GET, sprintf('/media/%s', $this->image_paths['custom']));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testRotateImageBasedOnExifOrientation(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        $client->request(Request::METHOD_GET, sprintf('/media/%s', $this->image_paths['sq']));

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testAdminMedia(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        $client->request(Request::METHOD_GET, sprintf('/admin/media/%s', $this->image_paths['sq']));
        $client->followRedirect();

        $this->assertEquals(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testAdminMediaFromAdmin(): void
    {
        /** @var User $admin */
        $admin = new User();
        $admin->fromArray(1, 'admin', 'passwd', ['ROLE_USER', 'ROLE_WEBMASTER']);

        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();

        $userProvider = $this->prophesize(UserProvider::class);
        $userProvider->supportsClass(Argument::any())->willReturn(true);
        $userProvider->refreshUser(Argument::any())->willReturn($admin);
        $container->set(UserProvider::class, $userProvider->reveal());

        $client->loginUser($admin);

        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        $client->request(Request::METHOD_GET, sprintf('/admin/media/%s', $this->image_paths['sq']));

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }
}
