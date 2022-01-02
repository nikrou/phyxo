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
use App\Form\Model\UserInfosModel;
use App\Form\Transformer\UserToUserInfosTransformer;
use App\Repository\LanguageRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserInfosType extends AbstractType
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
        $builder->addModelTransformer(new UserToUserInfosTransformer($this->languageRepository, $this->themeRepository, $this->userRepository));

        $fields = $builder->create('fields');
        $fields
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

        if ($options['form_group']) {
            $builder->add('user_preferences', FormGroupType::class, [
                'title' => $options['title'],
                'fields' => function(FormBuilderInterface $builder) use ($fields) {
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

        if ($options['with_submit_buttons']) {
            $builder
                ->add('validate', SubmitType::class, ['label' => 'Submit', 'row_attr' => ['class' => 'no_div'], 'attr' => ['class' => 'btn-raised btn-primary']])
                ->add('cancel', ResetType::class, ['label' => 'Reset', 'row_attr' => ['class' => 'no_div'], 'attr' => ['class' => 'btn-raised btn-info']]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'title' => 'Preferences',
            'form_group' => true,
            'with_submit_buttons' => true,
            'data_class' => UserInfosModel::class,
            'attr' => [
                'novalidate' => 'novalidate',
            ]
        ]);
        $resolver->setAllowedTypes('title', 'string');
        $resolver->setAllowedTypes('form_group', 'bool');
        $resolver->setAllowedTypes('with_submit_buttons', 'bool');
    }
}
