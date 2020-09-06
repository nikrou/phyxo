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
use App\Repository\UserRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class GroupNotificationSubscriber implements EventSubscriberInterface
{
    private $mailer, $em, $conf, $router, $template, $userMapper, $translator;

    public function __construct(MailerInterface $mailer, EntityManager $em, Conf $conf, RouterInterface $router, Environment $template,
                                UserMapper $userMapper, TranslatorInterface $translator)
    {
        $this->mailer = $mailer;
        $this->em = $em;
        $this->conf = $conf;
        $this->router = $router;
        $this->template = $template;
        $this->userMapper = $userMapper;
        $this->translator = $translator;
    }

    public static function getSubscribedEvents():array
    {
        return [
            GroupEvent::class => 'onGroupNotify'
        ];
    }

    public function onGroupNotify(GroupEvent $event)
    {
        $webmaster = $this->userMapper->getWebmaster();
        $subject = $this->translator->trans('[{title}] Visit album {album}', ['title' => $this->conf['gallery_title'], 'album' => $event->getCategory()['name']], 'admin');

        $params = [
            'GALLERY_TITLE' => $this->conf['gallery_title'],
            'CONTACT_MAIL' => $webmaster['mail_address'],
            'GALLERY_URL' => $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'CAT_NAME' => $event->getCategory()['name'],
            'LINK' => $this->router->generate('album', ['category_id' => $event->getCategory()['id']], UrlGeneratorInterface::ABSOLUTE_URL),
            'CPL_CONTENT' => $event->getMailContent() ? $event->getMailContent() : '',
            'IMG_URL' => $event->getImageUrl(),
            'MAIL_TITLE' => $subject,
            'MAIL_THEME' => $this->conf['mail_theme'],
            'LEVEL_SEPARATOR' => $this->conf['level_separator'],
            'CONTENT_ENCODING' => 'utf-8',
        ];

        $languages = [];
        $result = $this->em->getRepository(UserRepository::class)->getUsersByGroup($event->getGroup());
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            $languages[$row['language']][] = $row;
        }

        $message = (new TemplatedEmail())->subject($subject);

        foreach ($languages as $language => $users) {
            //@switch to language in template (@see switch_lang_to and switch_lang_back from Mail class)
            foreach ($users as $user) {
                $message->addBcc($user['email'], $user['name']);
            }
        }

        $message
          ->textTemplate('mail/text/cat_group_info.text.twig')
          ->htmlTemplate('mail/html/cat_group_info.html.twig')
          ->context($params);

        if (!empty($this->conf['mail_sender_email'])) {
            $from[] = $this->conf['mail_sender_email'];
            if (!empty($this->conf['mail_sender_name'])) {
                $from[] = $this->conf['mail_sender_name'];
            }
        } else {
            $from = [$webmaster['mail_address'], $webmaster['username']];
        }

        $message->from(...$from);
        $message->replyTo(...$from);

        $this->mailer->send($message);
    }
}
