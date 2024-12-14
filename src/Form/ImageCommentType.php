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

use App\DataMapper\UserMapper;
use App\Form\Model\ImageCommentModel;
use App\Security\AppUserService;
use App\Validator\SameAuthor;
use Phyxo\Conf;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ImageCommentType extends AbstractType
{
    public function __construct(private readonly UserMapper $userMapper, private Conf $conf, private readonly AppUserService $appUserService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$this->userMapper->isClassicUser()) {
            $authorConstraints = [];

            if ($this->conf['comments_author_mandatory']) {
                $authorConstraints[] = new NotBlank();
            }

            $authorConstraints[] = new SameAuthor();

            $builder->add(
                'author',
                TextType::class,
                [
                    'constraints' => $authorConstraints,
                    'required' => $this->conf['comments_author_mandatory'],
                    'empty_data' => 'guest'
                ]
            );
        }

        if (!$this->userMapper->isClassicUser() || in_array($this->appUserService->getUser()->getMailAddress(), [null, '', '0'], true)) {
            $builder->add(
                'mail_address',
                EmailType::class,
                [
                    'constraints' => $this->conf['comments_email_mandatory'] ? [new NotBlank()] : [],
                    'label' => 'Email address',
                    'required' => $this->conf['comments_email_mandatory']
                ]
            );
        }

        if ($this->conf['comments_enable_website']) {
            $builder->add('website_url', UrlType::class, ['label' => 'Website', 'required' => false, 'default_protocol' => 'https']);
        }

        $builder->add('content', TextareaType::class, ['label' => 'Comment', 'attr' => ['cols' => 50, 'rows' => 5], 'required' => true]);

        $builder->add('validate', SubmitType::class, ['label' => 'Submit', 'row_attr' => ['class' => 'no_div'], 'attr' => ['class' => 'btn-raised btn-primary']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ImageCommentModel::class,
            'attr' => [
                'novalidate' => 'novalidate',
            ]
        ]);
    }
}
