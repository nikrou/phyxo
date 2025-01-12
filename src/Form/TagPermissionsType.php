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

use App\Enum\UserStatusType;
use App\Form\Model\TagPermissionsModel;
use App\Form\Transformer\ConfToTagPermissionsTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TagPermissionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new ConfToTagPermissionsTransformer());

        $builder->add('who_add', FormGroupType::class, [
            'title' => 'Who can add tags?',
            'fields' => function (FormBuilderInterface $builder): void {
                $builder->add(
                    'tags_permission_add',
                    EnumType::class,
                    [
                        'label' => 'Who can add tags?',
                        'class' => UserStatusType::class,
                        'placeholder' => 'Choose an option',
                    ]
                );
                $builder->add('tags_existing_only', CheckboxType::class, ['label' => 'Only add existing tags']);
                $builder->add('tags_publish_immediately', CheckboxType::class, ['label' => 'Moderate added tags']);
            }
        ]);

        $builder->add('who_delete', FormGroupType::class, [
            'title' => 'Who can delete related tags?',
            'fields' => function (FormBuilderInterface $builder): void {
                $builder->add(
                    'tags_permission_delete',
                    EnumType::class,
                    [
                        'label' => 'Who can delete related tags?',
                        'class' => UserStatusType::class, 'expanded' => false,
                        'placeholder' => 'Choose an option',
                        'help' => 'Be careful, whatever the configuration value is, new tag can be deleted anyway',
                    ]
                );
                $builder->add(
                    'tags_delete_immediately',
                    CheckboxType::class,
                    [
                        'label' => 'Moderate deleted tags',
                        'help' => 'If a user delete a tag and you "moderate delete tags", then theses tags will be displayed to all users until you validate the deletion.'
                    ]
                );
            }
        ]);

        $builder->add('display_pending', FormGroupType::class, [
            'title' => 'Display for pending tags',
            'help' => 'By default, if you allow some users to add tags, theses tags are not shown to them (nor others users). And pending deleted tags are shown.',
            'fields' => function (FormBuilderInterface $builder): void {
                $builder->add(
                    'tags_show_pending_added',
                    CheckboxType::class,
                    [
                        'label' => 'Show added pending tags to the user who add them',
                        'help' => 'A css class is added to tag to show added pending tags differently to the user who add them'
                    ]
                );
                $builder->add(
                    'tags_show_pending_deleted',
                    CheckboxType::class,
                    [
                        'label' => 'Show deleted pending tags to the user who delete them',
                        'help' => 'A css class is added to tag to show deleted pending tags differently to the user who delete them'
                    ]
                );
            }
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TagPermissionsModel::class,
            'translation_domain' => 'admin',
            'attr' => [
                'novalidate' => 'novalidate',
            ]
        ]);
    }
}
