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
use App\Enum\UserPrivacyLevelType;
use App\Enum\UserStatusType;
use App\Form\Model\UserInfosModel;
use App\Form\Transformer\UserToUserInfosTransformer;
use App\Repository\LanguageRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserInfosType extends AbstractType
{
    final public const IN_ADMIN_OPTION = 'in_admin';
    final public const TITLE_OPTION = 'title';
    final public const FORM_GROUP_OPTION = 'form_group';
    final public const WITH_SUBMIT_BUTTONS_OPTION = 'with_submit_buttons';

    public function __construct(
        private readonly LanguageRepository $languageRepository,
        private readonly ThemeRepository $themeRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new UserToUserInfosTransformer($this->languageRepository, $this->themeRepository, $this->userRepository));

        $fields = $builder->create('fields');
        $fields->add(
            'nb_image_page',
            TextType::class,
            [
                'label' => 'Number of photos per page',
                'attr' => ['placeholder' => 'Number of photos per page'], 'required' => false
            ]
        );

        $fields->add(
            'theme',
            EntityType::class,
            [
                'class' => Theme::class,
                'choice_label' => 'name',
                'choice_value' => 'id',
                'label' => 'Theme',
                'required' => true
            ]
        );
        $fields->add(
            'language',
            EntityType::class,
            [
                'class' => Language::class,
                'choice_label' => 'name',
                'choice_value' => 'id',
                'label' => 'Language',
                'required' => true,
            ]
        );

        if ($options[self::IN_ADMIN_OPTION]) {
            $fields->add('status', EnumType::class, ['label' => 'Status', 'class' => UserStatusType::class, 'expanded' => false]);
            $fields->add('level', EnumType::class, ['label' => 'Privacy level', 'class' => UserPrivacyLevelType::class, 'expanded' => false]);
        }

        $fields->add(
            'recent_period',
            TextType::class,
            [
                'label' => 'Recent period',
                'attr' => ['placeholder' => 'Recent period'], 'required' => false
            ]
        );
        $fields->add(
            'expand',
            ChoiceType::class,
            [
                'label' => 'Expand all albums',
                'choices' => ['Yes' => true, 'No' => false],
                'expanded' => true,
                'required' => true
            ]
        );
        $fields->add(
            'show_nb_comments',
            ChoiceType::class,
            [
                'label' => 'Show number of comments',
                'choices' => ['Yes' => true, 'No' => false],
                'expanded' => true,
                'required' => true
            ]
        );
        $fields->add(
            'show_nb_hits',
            ChoiceType::class,
            [
                'label' => 'Show number of hits',
                'choices' => ['Yes' => true, 'No' => false],
                'expanded' => true,
                'required' => true
            ]
        );

        if ($options[self::FORM_GROUP_OPTION]) {
            $builder->add('user_preferences', FormGroupType::class, [
                'title' => $options[self::TITLE_OPTION],
                'fields' => function (FormBuilderInterface $builder) use ($fields): void {
                    foreach ($fields->all() as $child) {
                        $builder->add($child);
                    }
                }
            ]);
        } else {
            foreach ($fields->all() as $child) {
                $builder->add($child);
            }
        }

        if ($options[self::WITH_SUBMIT_BUTTONS_OPTION]) {
            $builder
                ->add('validate', SubmitType::class, ['label' => 'Submit', 'row_attr' => ['class' => 'no_div'], 'attr' => ['class' => 'btn-raised btn-primary']])
                ->add('cancel', ResetType::class, ['label' => 'Reset', 'row_attr' => ['class' => 'no_div'], 'attr' => ['class' => 'btn-raised btn-info']]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            self::TITLE_OPTION => 'Preferences',
            self::FORM_GROUP_OPTION => true,
            self::WITH_SUBMIT_BUTTONS_OPTION => true,
            self::IN_ADMIN_OPTION => false,
            'data_class' => UserInfosModel::class,
            'attr' => ['novalidate' => 'novalidate'],
            'translation_domain' => 'admin',
        ]);
        $resolver->setAllowedTypes(self::TITLE_OPTION, 'string');
        $resolver->setAllowedTypes(self::FORM_GROUP_OPTION, 'bool');
        $resolver->setAllowedTypes(self::WITH_SUBMIT_BUTTONS_OPTION, 'bool');
        $resolver->setAllowedTypes(self::IN_ADMIN_OPTION, 'bool');
    }
}
