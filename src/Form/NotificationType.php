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

use App\Form\Model\NotificationModel;
use App\Form\Transformer\ConfToNotificationTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NotificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new ConfToNotificationTransformer());

        $builder->add(
            'params',
            FormGroupType::class,
            [
                'title' => 'Parameters',
                'fields' => function (FormBuilderInterface $builder): void {
                    $builder->add('nbm_send_html_mail', CheckboxType::class, ['label' => 'Send mail on HTML format']);
                    $builder->add(
                        'nbm_send_mail_as',
                        TextType::class,
                        [
                            'label' => 'Send mail as',
                            'help' => 'With blank value, gallery title will be used'
                        ]
                    );
                    $builder->add('nbm_send_detailed_content', CheckboxType::class, ['label' => 'Add detailed content']);
                    $builder->add('nbm_complementary_mail_content', TextType::class, ['label' => 'Complementary mail content']);
                    $builder->add(
                        'nbm_send_recent_post_dates',
                        CheckboxType::class,
                        [
                            'label' => 'Include display of recent photos grouped by dates',
                            'help' => 'Available only with HTML format'
                        ]
                    );
                }
            ]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NotificationModel::class,
            'attr' => ['novalidate' => 'novalidate'],
            'translation_domain' => 'admin',
        ]);
    }
}
