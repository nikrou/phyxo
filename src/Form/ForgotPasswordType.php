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

use App\Form\Model\ForgotPasswordModel;
use App\Form\Transformer\IdentifierToUserTransformer;
use App\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForgotPasswordType extends AbstractType
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new IdentifierToUserTransformer($this->userRepository));

        $builder->add(
            'identifier',
            TextType::class,
            [
                'label' => 'Username or email',
                'help' => 'You will receive a link to create a new password via email.',
                'attr' => ['placeholder' => 'Please enter your username or email address.'],
                'required' => false
            ]
        );
        $builder->add('validate', SubmitType::class, ['label' => 'Change my password', 'row_attr' => ['class' => 'no_div'], 'attr' => ['class' => 'btn-raised btn-primary']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForgotPasswordModel::class,
            'attr' => [
                'novalidate' => 'novalidate',
            ]
        ]);
    }
}
