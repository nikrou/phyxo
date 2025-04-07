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

use App\Entity\Comment;
use Phyxo\Conf;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EditCommentType extends AbstractType
{
    public function __construct(private readonly Conf $conf)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('redirect', HiddenType::class, ['data' => $options['redirect'], 'mapped' => false]);

        if ($this->conf['comments_enable_website']) {
            $builder->add('website_url', UrlType::class, ['label' => 'Website', 'required' => false,  'default_protocol' => 'https']);
        }

        $builder->add('content', TextareaType::class, ['label' => 'Comment', 'attr' => ['cols' => 50, 'rows' => 5], 'required' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => Comment::class,
                'attr' => [
                    'novalidate' => 'novalidate',
                ],
                'redirect' => '',
            ])
            ->addAllowedTypes('redirect', 'string');
    }
}
