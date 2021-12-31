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

use App\Form\Model\PasswordResetModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PasswordResetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'new_password',
            RepeatedType::class,
            [
                'type' => PasswordType::class,
                'invalid_message' => 'The passwords do not match',
                'first_options' => ['label' => 'New password', 'attr' => ['placeholder' => 'Your new password']],
                'second_options' => ['label' => 'Confirm password', 'attr' => ['placeholder' => 'Confirm password']],
                'attr' => ['placeholder' => 'Password'],
                'required' => false
            ]
        );

        $builder->add('validate', SubmitType::class, ['label' => 'Submit', 'row_attr' => ['class' => 'no_div'], 'attr' => ['class' => 'btn-raised btn-primary']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PasswordResetModel::class,
            'attr' => [
                'novalidate' => 'novalidate',
            ]
        ]);
    }
}
