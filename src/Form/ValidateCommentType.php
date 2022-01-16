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

use App\Entity\Comment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ValidateCommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('id', HiddenType::class, ['data' => $options['id'], 'mapped' => false]);

        $builder->add('redirect', HiddenType::class, ['data' => $options['redirect'], 'mapped' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => Comment::class,
                'attr' => [
                    'novalidate' => 'novalidate',
                ],
                'id' => null,
                'redirect' => ''
            ])
            ->addAllowedTypes('id', 'int')
            ->addAllowedTypes('redirect', 'string');
    }
}
