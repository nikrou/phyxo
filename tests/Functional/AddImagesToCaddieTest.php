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

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Caddie;
use App\Tests\Factory\ImageFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AddImagesToCaddieTest extends ApiTestCase
{
    use ResetDatabase;
    use Factories;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAddImagesToCaddie(): void
    {
        $user = UserFactory::createOne();
        $images = ImageFactory::createMany(2);

        $client = static::createClient();
        $client->loginUser($user);

        $client->request('POST', '/api/caddy/add', [
            'json' => [
                'userId' => $user->getId(),
                'imageIds' => [$images[0]->getId(), $images[1]->getId()],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $caddieRepository = $this->entityManager->getRepository(Caddie::class);
        $caddies = $caddieRepository->findBy(['user' => $user->getId()]);

        $this->assertCount(2, $caddies);
        $this->assertSame($images[0]->getId(), $caddies[0]->getImage()->getId());
        $this->assertSame($images[1]->getId(), $caddies[1]->getImage()->getId());

        // don't know how to return message when using State processor
        // $this->assertJsonContains(['message' => 'Images added to caddie successfully']);
    }
}
