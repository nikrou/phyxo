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

use App\Form\Model\UserProfileModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserCreationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'username',
            TextType::class,
            ['attr' => ['placeholder' => 'Username'],
                'required' => true,
                'constraints' => [new NotBlank(['message' => 'Username is mandatory'])]
            ]
        );
        $builder->add(
            'current_password',
            PasswordType::class,
            [
                'attr' => ['placeholder' => 'User password'],
                'label' => 'Password',
                'required' => false,
            ]
        );

        $builder->add(
            'mail_address',
            EmailType::class,
            [
                'label' => 'Email address',
                'attr' => ['placeholder' => 'Email address'], 'required' => false,
            ]
        );

        $builder->add('validate', SubmitType::class, ['label' => 'Submit', 'row_attr' => ['class' => 'no_div'], 'attr' => ['class' => 'btn-raised btn-primary']]);
        $builder->add('cancel', ResetType::class, ['label' => 'Reset', 'row_attr' => ['class' => ''], 'attr' => ['class' => 'btn-raised btn-info']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserProfileModel::class,
            'attr' => [
                'novalidate' => 'novalidate',
            ]
        ]);
    }
}
