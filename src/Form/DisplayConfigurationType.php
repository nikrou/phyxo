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

use App\Form\Model\DisplayConfigurationModel;
use App\Form\Transformer\ConfToDisplayConfigurationTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

class DisplayConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new ConfToDisplayConfigurationTransformer());

        $builder->add('main_page', FormGroupType::class, [
            'title' => 'Main page',
            'fields' => function (FormBuilderInterface $builder): void {
                $builder->add(
                    'menubar_filter_icon',
                    CheckboxType::class,
                    ['label' => 'Activate icon "Display only recently posted photos"']
                );
                $builder->add(
                    'index_new_icon',
                    CheckboxType::class,
                    ['label' => 'Activate icon "new" next to albums and pictures']
                );
                $builder->add(
                    'index_sort_order_input',
                    CheckboxType::class,
                    ['label' => 'Activate icon "Sort order"']
                );
                $builder->add(
                    'index_flat_icon',
                    CheckboxType::class,
                    ['label' => 'Activate icon "Display all photos in all sub-albums"']
                );
                $builder->add(
                    'index_posted_date_icon',
                    CheckboxType::class,
                    ['label' => 'Activate icon "Display a calendar by posted date"']
                );
                $builder->add(
                    'index_created_date_icon',
                    CheckboxType::class,
                    ['label' => 'Activate icon "Display a calendar by creation date"']
                );
                $builder->add(
                    'nb_albums_page',
                    IntegerType::class,
                    [
                        'label' => 'Number of albums per page',
                        'constraints' => new GreaterThan(4, null, 'The number of albums per page must be above 4')
                    ]
                );
            }
        ]);

        $builder->add('photo_page', FormGroupType::class, [
            'title' => 'Photo page',
            'fields' => function (FormBuilderInterface $builder): void {
                $builder->add(
                    'picture_metadata_icon',
                    CheckboxType::class,
                    ['label' => 'Activate icon "Show file metadata"']
                );
                $builder->add(
                    'picture_download_icon',
                    CheckboxType::class,
                    ['label' => 'Activate icon "Download this file"']
                );
                $builder->add(
                    'picture_favorite_icon',
                    CheckboxType::class,
                    ['label' => 'Activate icon "Add this photo to your favorites"']
                );
                $builder->add('picture_navigation_icons', CheckboxType::class, ['label' => 'Activate navigation bar']);
                $builder->add('picture_navigation_thumb', CheckboxType::class, ['label' => 'Activate navigation thumbnails']);
                $builder->add('picture_menu', CheckboxType::class, ['label' => 'Show menubar']);
            }
        ]);

        $builder->add('photo_properties', FormGroupType::class, [
            'title' => 'Photo properties',
            'fields' => function (FormBuilderInterface $builder): void {
                $builder->add('author', CheckboxType::class);
                $builder->add('created_on', CheckboxType::class);
                $builder->add('posted_on', CheckboxType::class);
                $builder->add('dimensions', CheckboxType::class);
                $builder->add('file', CheckboxType::class);
                $builder->add('filesize', CheckboxType::class);
                $builder->add('tags', CheckboxType::class);
                $builder->add('albums', CheckboxType::class);
                $builder->add('visits', CheckboxType::class);
                $builder->add('rating_score', CheckboxType::class);
                $builder->add('privacy_level', CheckboxType::class, ['label' => 'Who can see this photo?', 'help' => 'available for administrators only']);
            }
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DisplayConfigurationModel::class,
            'translation_domain' => 'admin',
            'attr' => [
                'novalidate' => 'novalidate',
            ]
        ]);
    }
}
