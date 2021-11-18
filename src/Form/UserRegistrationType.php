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

use App\Form\Model\UserModel;
use Phyxo\Conf;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserRegistrationType extends AbstractType
{
    private Conf $conf;

    public function __construct(Conf $conf)
    {
        $this->conf = $conf;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $emailConstraints = [];
        if ($this->conf['obligatory_user_mail_address']) {
            $emailConstraints = [new NotBlank(['message' => 'Please enter your email'])];
        }

        $builder
            ->add('username', TextType::class, ['attr' => ['placeholder' => 'Username'], 'required' => true])
            ->add(
                'password',
                RepeatedType::class,
                [
                    'type' => PasswordType::class,
                    'invalid_message' => 'The passwords do not match',
                    'first_options' => ['label' => 'Password', 'attr' => ['placeholder' => 'Password']],
                    'second_options' => ['label' => 'Confirm password', 'attr' => ['placeholder' => 'Confirm password']],
                    'attr' => ['placeholder' => 'Password'],
                    'required' => true
                ]
            )
            ->add(
                'mail_address',
                EmailType::class,
                [
                    'label' => 'Email address',
                    'help' => '(useful when password forgotten)',
                    'attr' => ['placeholder' => 'Email address'], 'required' => $this->conf['obligatory_user_mail_address'],
                    'constraints' => $emailConstraints
                ]
            )
            ->add(
                'send_password_by_mail',
                CheckboxType::class,
                ['label' => 'Send connection settings by email', 'required' => false, 'mapped' => false]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserModel::class,
            'attr' => [
                'novalidate' => 'novalidate',
            ]
        ]);
    }
}
