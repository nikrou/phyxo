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
use App\Enum\UserStatusType;
use App\Repository\ImageRepository;
use App\Security\UserProvider;
use App\Tests\Factory\UserFactory;
use DateTime;
use Phyxo\Image\ImageStandardParams;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MediaControllerTest extends WebTestCase
{
    use ProphecyTrait;
    use ResetDatabase;
    use Factories;

    private KernelBrowser $client;
    private string $fixtures_dir = __DIR__ . '/../fixtures/media';
    private string $sample_image = 'sample.jpg';
    private array $image_paths = [];
    private string $derivative_path = '';
    private ObjectProphecy $imageRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        UserFactory::new(['username' => 'guest'])->withUserInfos(['status' => UserStatusType::GUEST])->create();

        $container = self::getContainer();
        $uploadDir = $container->getParameter('upload_dir');
        $mediaCacheDir = $container->getParameter('media_cache_dir');

        $now = new DateTime('now');
        $base_image_path = sprintf('tests/upload/%s/%s-%s', $now->format('Y/m/d'), $now->format('YmdHis'), substr(md5($this->sample_image), 0, 8));
        $image_upload_path = sprintf('%s/%s.jpg', $container->getParameter('root_project_dir'), $base_image_path);
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
        $this->client->request(Request::METHOD_GET, '/media/tests/upload/dummy-sq.jpg');
        self::assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testGenerateImage(): void
    {
        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        $this->client->request(Request::METHOD_GET, sprintf('/media/%s', $this->image_paths['sq']));
        self::assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testReusedGenerateImage(): void
    {
        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        $this->client->request(Request::METHOD_GET, sprintf('/media/%s', $this->image_paths['sq']));

        self::assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertTrue(
            $this->client->getResponse()->headers->contains(
                'Content-Type',
                'image/jpeg'
            ),
            'the "Content-Type" header is "image/jpeg"'
        );

        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        // same path, image not changed so http status code must be 304
        $this->client->request(
            Request::METHOD_GET,
            sprintf('/media/%s', $this->image_paths['sq']),
            [],
            [],
            [
                'HTTP_If-Modified-Since' => gmdate('D, d M Y H:i:s', filemtime($this->derivative_path)) . ' GMT',
                'HTTP_If-None-Match' => '"' . md5_file($this->derivative_path) . '"',
                'HTTP_Etag' => '"' . md5_file($this->derivative_path) . '"',
            ]
        );

        self::assertEquals(Response::HTTP_NOT_MODIFIED, $this->client->getResponse()->getStatusCode());
    }

    public function testGenerateUnknownCustomSizeNotAllowed(): void
    {
        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        $this->client->request(Request::METHOD_GET, sprintf('/media/%s', $this->image_paths['unknown']));
        self::assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testGenerateCustomImage(): void
    {
        $width = 1200;
        $height = 900;
        $custom = sprintf('e%dx%d', $width, $height);

        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        $image_std_params = $container->get(ImageStandardParams::class);
        $image_std_params->makeCustom($width, $height, 1, $width, $height);
        self::assertTrue($image_std_params->hasCustom($custom));

        $this->client->request(Request::METHOD_GET, sprintf('/media/%s', $this->image_paths['custom']));
        self::assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testRotateImageBasedOnExifOrientation(): void
    {
        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        $this->client->request(Request::METHOD_GET, sprintf('/media/%s', $this->image_paths['sq']));
        self::assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminMedia(): void
    {
        $container = static::getContainer();
        $container->set(ImageRepository::class, $this->imageRepository->reveal());

        $this->client->request(Request::METHOD_GET, sprintf('/admin/media/%s', $this->image_paths['sq']));
        $this->client->followRedirect();
        self::assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminMediaFromAdmin(): void
    {
        /** @var User $admin */
        $admin = new User();
        $admin->fromArray(1, 'admin', 'passwd', ['ROLE_USER', 'ROLE_WEBMASTER']);

        $container = static::getContainer();

        $userProvider = $this->prophesize(UserProvider::class);
        $userProvider->supportsClass(Argument::any())->willReturn(true);
        $userProvider->refreshUser(Argument::any())->willReturn($admin);
        $container->set(UserProvider::class, $userProvider->reveal());

        $this->client->loginUser($admin);

        $container->set(ImageRepository::class, $this->imageRepository->reveal());
        $this->client->request(Request::METHOD_GET, sprintf('/admin/media/%s', $this->image_paths['sq']));
        self::assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }
}
