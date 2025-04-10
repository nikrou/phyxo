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
use App\Form\Transformer\UserToUserProfileTransformer;
use App\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Email;

class UserProfileType extends AbstractType
{
    final public const string IN_ADMIN_OPTION = 'in_admin';
    final public const string RESET_BUTTON = 'reset_button';

    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new UserToUserProfileTransformer($this->userRepository));

        $fields = $builder->create('fields');

        if ($options[self::IN_ADMIN_OPTION]) {
            $fields->add('username', TextType::class, ['required' => true]);
        } else {
            $fields->add(
                'username',
                TextType::class,
                [
                    'attr' => ['readonly' => true, 'class' => 'form-control-plaintext'],
                    'required' => false,
                ]
            );
        }

        if ($options[self::IN_ADMIN_OPTION]) {
            $fields->add(
                'current_password',
                PasswordType::class,
                [
                    'attr' => ['placeholder' => 'User password'],
                    'label' => 'Password',
                    'required' => false,
                ]
            );
        } else {
            $fields->add(
                'current_password',
                PasswordType::class,
                [
                    'constraints' => [new UserPassword()],
                    'attr' => ['placeholder' => 'Your current password'],
                    'label' => 'Current password',
                    'required' => false,
                ]
            );

            $fields->add(
                'new_password',
                RepeatedType::class,
                [
                    'type' => PasswordType::class,
                    'invalid_message' => 'The passwords do not match',
                    'first_options' => ['label' => 'New password', 'attr' => ['placeholder' => 'Your new password']],
                    'second_options' => ['label' => 'Confirm password', 'attr' => ['placeholder' => 'Confirm password']],
                    'attr' => ['placeholder' => 'Password'],
                    'required' => false,
                ]
            );
        }

        $fields->add(
            'mail_address',
            EmailType::class,
            [
                'constraints' => [new Email(['message' => 'Please enter a valid mail address. Ex: john.doe@phyxo.net'])],
                'label' => 'Email address',
                'attr' => ['placeholder' => 'Email address'],
                'required' => false,
            ]
        );

        $builder->add('user_fields', FormGroupType::class, [
            'title' => 'User authentication',
            'fields' => function (FormBuilderInterface $builder) use ($fields): void {
                foreach ($fields->all() as $child) {
                    $builder->add($child);
                }
            },
        ]);

        $builder->add('id', HiddenType::class);
        $builder->add('user_infos', UserInfosType::class, ['label' => false, UserInfosType::WITH_SUBMIT_BUTTONS_OPTION => false, self::IN_ADMIN_OPTION => true]);

        $builder->add('validate', SubmitType::class, ['label' => 'Submit', 'row_attr' => ['class' => 'no_div'], 'attr' => ['class' => 'btn-raised btn-primary']]);
        $builder->add('cancel', ResetType::class, ['label' => 'Reset', 'row_attr' => ['class' => ''], 'attr' => ['class' => 'btn-raised btn-info']]);

        if ($options[self::RESET_BUTTON]) {
            $builder->add('resetToDefault', SubmitType::class, ['label' => 'Reset to default values', 'row_attr' => ['class' => ''], 'attr' => ['class' => 'btn-raised btn-warning']]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserProfileModel::class,
            'attr' => ['novalidate' => 'novalidate'],
            self::IN_ADMIN_OPTION => false,
            self::RESET_BUTTON => true,
        ]);

        $resolver->setAllowedTypes(self::IN_ADMIN_OPTION, 'bool');
        $resolver->setAllowedTypes(self::RESET_BUTTON, 'bool');
    }
}
