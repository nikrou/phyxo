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

use App\Entity\Language;
use App\Entity\Theme;
use App\Form\Model\UserProfileModel;
use App\Repository\LanguageRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
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
    private $languageRepository, $themeRepository, $userRepository;

    public function __construct(LanguageRepository $languageRepository, ThemeRepository $themeRepository, UserRepository $userRepository)
    {
        $this->languageRepository = $languageRepository;
        $this->themeRepository = $themeRepository;
        $this->userRepository = $userRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new UserToUserProfileTransformer($this->languageRepository, $this->themeRepository, $this->userRepository));

        $builder->add('user_fields', FormGroupType::class, [
            'title' => 'User authentication',
            'fields' => function(FormBuilderInterface $builder) {
                $builder
                    ->add(
                        'username',
                        TextType::class,
                        [
                            'attr' => ['readonly' => true, 'class' => 'form-control-plaintext'],
                            'required' => false
                        ]
                    )
                    ->add(
                        'mail_address',
                        EmailType::class,
                        [
                            'constraints' => [new Email(['message' => "Please enter a valid mail address. Ex: john.doe@phyxo.net"])],
                            'label' => 'Email address',
                            'attr' => ['placeholder' => 'Email address'],
                            'required' => false
                        ]
                    )
                    ->add(
                        'current_password',
                        PasswordType::class,
                        [
                            'constraints' => [new UserPassword()],
                            'attr' => ['placeholder' => 'Your current password'],
                            'label' => 'Current password',
                            'required' => false,
                        ]
                    )
                    ->add(
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
            }
        ]);

        $builder->add('user_preferences', FormGroupType::class, [
            'title' => 'Preferences',
            'fields' => function(FormBuilderInterface $builder) {
                $builder
                    ->add(
                        'nb_image_page',
                        TextType::class,
                        [
                            'label' => 'Number of photos per page',
                            'attr' => ['placeholder' => 'Number of photos per page'], 'required' => false
                        ]
                    )
                    ->add(
                        'theme',
                        EntityType::class,
                        [
                            'class' => Theme::class,
                            'choice_label' => 'name',
                            'choice_value' => 'id',
                            'label' => 'Theme',
                            'required' => true
                        ]
                    )
                    ->add(
                        'language',
                        EntityType::class,
                        [
                            'class' => Language::class,
                            'choice_label' => 'name',
                            'choice_value' => 'id',
                            'label' => 'Language',
                            'required' => true,
                        ]
                    )
                    ->add(
                        'recent_period',
                        TextType::class,
                        [
                            'label' => 'Recent period',
                            'attr' => ['placeholder' => 'Recent period'], 'required' => false
                        ]
                    )
                    ->add(
                        'expand',
                        ChoiceType::class,
                        [
                            'label' => 'Expand all albums',
                            'choices' => ['Yes' => true, 'No' => false],
                            'expanded' => true,
                            'required' => true
                        ]
                    )
                    ->add(
                        'show_nb_comments',
                        ChoiceType::class,
                        [
                            'label' => 'Show number of comments',
                            'choices' => ['Yes' => true, 'No' => false],
                            'expanded' => true,
                            'required' => true
                        ]
                    )
                    ->add(
                        'show_nb_hits',
                        ChoiceType::class,
                        [
                            'label' => 'Show number of hits',
                            'choices' => ['Yes' => true, 'No' => false],
                            'expanded' => true,
                            'required' => true
                        ]
                    );
            }
        ]);

        $builder
        ->add('validate', SubmitType::class, ['label' => 'Submit', 'row_attr' => ['class' => 'no_div'], 'attr' => ['class' => 'btn-raised btn-primary']])
        ->add('cancel', ResetType::class, ['label' => 'Reset', 'row_attr' => ['class' => ''], 'attr' => ['class' => 'btn-raised btn-info']])
        ->add('resetToDefault', SubmitType::class, ['label' => 'Reset to default values', 'row_attr' => ['class' => ''], 'attr' => ['class' => 'btn-raised btn-warning']]);
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
