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

use App\Entity\User;
use App\Form\Model\SearchRulesModel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HistorySearchType extends AbstractType
{
    final public const array TYPES = [
        'none' => 'none',
        'picture' => 'picture',
        'high' => 'high',
        'other' => 'other',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $display_thumbnails = [
            'No display' => 'no_display_thumbnail',
            'Classic display' => 'display_thumbnail_classic',
            'Hoverbox display' => 'display_thumbnail_hoverbox',
        ];

        $builder->add('start', DateType::class, ['label' => 'Start date', 'widget' => 'single_text', 'required' => false]);
        $builder->add('end', DateType::class, ['label' => 'End date', 'widget' => 'single_text', 'required' => false]);
        $builder->add('types', ChoiceType::class, ['label' => 'Element type', 'choices' => self::TYPES, 'multiple' => true, 'required' => false]);
        $builder->add('image_id', IntegerType::class, ['required' => false]);
        $builder->add('filename', TextType::class, ['required' => false]);
        $builder->add('display_thumbnail', ChoiceType::class, ['choices' => $display_thumbnails]);
        $builder->add('user', EntityType::class, ['class' => User::class, 'choice_label' => 'username', 'choice_value' => 'id', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SearchRulesModel::class,
            'attr' => [
                'novalidate' => 'novalidate',
            ],
        ]);
    }
}
