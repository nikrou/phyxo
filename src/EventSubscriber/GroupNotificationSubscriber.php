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

namespace App\EventSubscriber;

use App\DataMapper\UserMapper;
use App\Events\GroupEvent;
use Phyxo\Conf;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class GroupNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private Conf $conf,
        private readonly RouterInterface $router,
        private readonly UserMapper $userMapper,
        private readonly TranslatorInterface $translator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            GroupEvent::class => 'onGroupNotify'
        ];
    }

    public function onGroupNotify(GroupEvent $event): void
    {
        $webmaster = $this->userMapper->getWebmaster();
        $subject = $this->translator->trans('[{title}] Visit album {album}', ['title' => $this->conf['gallery_title'], 'album' => $event->getCategory()['name']], 'admin');

        $params = [
            'GALLERY_TITLE' => $this->conf['gallery_title'],
            'CONTACT_MAIL' => $webmaster->getMailAddress(),
            'GALLERY_URL' => $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'CAT_NAME' => $event->getCategory()['name'],
            'LINK' => $this->router->generate('album', ['category_id' => $event->getCategory()['id']], UrlGeneratorInterface::ABSOLUTE_URL),
            'CPL_CONTENT' => $event->getMailContent() ?: '',
            'IMG_URL' => $event->getImageUrl(),
            'MAIL_TITLE' => $subject,
            'MAIL_THEME' => $this->conf['mail_theme'],
            'CONTENT_ENCODING' => 'utf-8',
        ];

        $languages = [];
        foreach ($this->userMapper->getRepository()->getUsersByGroup($event->getGroup()) as $user) {
            $languages[$user->getUserInfos()->getLanguage()][] = $user;
        }

        $message = (new TemplatedEmail())->subject($subject);

        foreach ($languages as $language => $users) {
            //@switch to language in template (@see switch_lang_to and switch_lang_back from Mail class)
            foreach ($users as $user) {
                $message->addBcc($user->getMailAddress(), $user->getUsername());
            }
        }

        $message
          ->textTemplate('mail/text/cat_group_info.text.twig')
          ->htmlTemplate('mail/html/cat_group_info.html.twig')
          ->context($params);

        if (!empty($this->conf['mail_sender_email'])) {
            if (!empty($this->conf['mail_sender_name'])) {
                $from = new Address($this->conf['mail_sender_email'], $this->conf['mail_sender_name']);
            } else {
                $from = new Address($this->conf['mail_sender_email']);
            }
        } else {
            $from = new Address($webmaster->getMailAddress(), $webmaster->getUsername());
        }

        $message->from($from);
        $message->replyTo($from);

        $this->mailer->send($message);
    }
}
