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

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use App\Model\AddImagesToCaddieInput;
use App\Repository\CaddieRepository;
use App\State\AddImagesToCaddieProcessor;
use ArrayObject;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'caddie')]
#[ORM\Entity(repositoryClass: CaddieRepository::class)]
#[ApiResource(operations: [
    new Post(
        uriTemplate: '/caddy/add',
        name: 'add_images_to_caddy',
        description: 'Add images to caddy',
        input: AddImagesToCaddieInput::class,
        processor: AddImagesToCaddieProcessor::class,
        openapi: new Operation(
            responses: [
                '200' => new Response(description: 'Ok'),
            ],
            summary: 'Add a book to the library.',
            description: 'My awesome operation',
            requestBody: new RequestBody(
                content: new ArrayObject(
                    [
                        'application/ld+json' => [
                            'schema' => [
                                'properties' => [
                                    'imageIds' => ['type' => 'array'],
                                ],
                            ],
                            'example' => [
                                'imageIds' => [12, 34, 5],
                            ],
                        ],
                    ]
                )
            )
        )
    ),
])]
class Caddie
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'caddies')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private User $user;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Image::class)]
    #[ORM\JoinColumn(name: 'element_id', nullable: false)]
    private Image $image;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(?Image $image): self
    {
        $this->image = $image;

        return $this;
    }
}
