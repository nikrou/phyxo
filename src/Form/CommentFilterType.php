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

namespace App\Form;

use App\Entity\Album;
use App\Form\Model\CommentFilterModel;
use App\Repository\AlbumRepository;
use App\Security\AppUserService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommentFilterType extends AbstractType
{
    public function __construct(private AppUserService $appUserService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $now = new \DateTimeImmutable();
        $since_options = [
            'today' => $now->sub(new \DateInterval('P1D')),
            'last 7 days' => $now->sub(new \DateInterval('P7D')),
            'last 30 days' => $now->sub(new \DateInterval('P30D')),
            'the beginning' => null
        ];

        $sort_order_options = [
            'descending' => 'DESC',
            'ascending' => 'ASC'
        ];

        $sort_by_options = [
            'comment date' => 'date',
            'photo' => 'image_id'
        ];

        $items_number_options = [5 => 5, 10 => 10, 20 => 20, 50 => 50, 'All comments' => null];

        $builder
            ->setMethod(Request::METHOD_GET)

            ->add('keyword', TextType::class, ['required' => false])

            ->add('author', TextType::class, ['required' => false])

            ->add(
                'album',
                EntityType::class,
                [
                    'class' => Album::class,
                    'query_builder' => fn(AlbumRepository $albumRepository) => $albumRepository->getQueryBuilderForFindAllowedAlbums($this->appUserService->getUser()->getUserInfos()->getForbiddenAlbums()),
                    'choice_label' => 'name',
                    'choice_value' => 'id',
                    'required' => false,
                ]
            )

            ->add(
                'since',
                ChoiceType::class,
                [
                    'choices' => $since_options,
                    'required' => false
                ]
            )

            ->add(
                'sort_by',
                ChoiceType::class,
                [
                    'choices' => $sort_by_options,
                    'data' => 'date',
                    'required' => false
                ]
            )

            ->add(
                'sort_order',
                ChoiceType::class,
                [
                    'choices' => $sort_order_options,
                    'data' => 'DESC',
                    'required' => false
                ]
            )

            ->add(
                'items_number',
                ChoiceType::class,
                [
                    'choices' => $items_number_options,
                    'required' => false
                ]
            )

            ->add('submit', SubmitType::class, ['label' => 'Filter and display', 'attr' => ['class' => 'btn btn-primary btn-raised']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'data_class' => CommentFilterModel::class,
                'novalidate' => 'novalidate',
            ]
        ]);
    }
}
