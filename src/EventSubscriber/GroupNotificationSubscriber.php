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
use App\Security\UserProvider;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Template\AdminTemplate;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class GroupNotificationSubscriber implements EventSubscriberInterface
{
    private $mailer, $em, $conf, $router, $template, $userProvider, $phyxoVersion, $phyxoWebsite, $userMapper, $translator;

    public function __construct(\Swift_Mailer $mailer, EntityManager $em, Conf $conf, RouterInterface $router, AdminTemplate $template,
                                UserProvider $userProvider, string $phyxoVersion, string $phyxoWebsite, UserMapper $userMapper, TranslatorInterface $translator)
    {
        $this->mailer = $mailer;
        $this->em = $em;
        $this->conf = $conf;
        $this->router = $router;
        $this->template = $template;
        $this->userProvider = $userProvider;
        $this->phyxoVersion = $phyxoVersion;
        $this->phyxoWebsite = $phyxoWebsite;
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
        $subject = $this->translator->trans('[{title}] Visit album {album}s', ['title' => $this->conf['gallery_title'], 'album' => $event->getCategory()['name']]);

        $params = [
            'GALLERY_TITLE' => $this->conf['gallery_title'],
            'CONTACT_MAIL' => $webmaster['mail_address'],
            'GALLERY_URL' => $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'CAT_NAME' => $event->getCategory()['name'],
            'LINK' => $this->router->generate('album', ['category_id' => $event->getCategory()['id']], UrlGeneratorInterface::ABSOLUTE_URL),
            'CPL_CONTENT' => $event->getMailContent() ? htmlentities($event->getMailContent(), ENT_QUOTES, 'utf-8') : '',
            'IMG_URL' => $event->getImageUrl(),
            'MAIL_TITLE' => $subject,
            'MAIL_THEME' => $this->conf['mail_theme'],
            'LEVEL_SEPARATOR' => $this->conf['level_separator'],
            'CONTENT_ENCODING' => 'utf-8',
            'PHYXO_VERSION' => $this->conf['show_version'] ? $this->phyxoVersion : '',
            'PHYXO_URL' => $this->phyxoWebsite,
        ];

        $languages = [];
        $result = $this->em->getRepository(UserRepository::class)->getUsersByGroup($event->getGroup());
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            $languages[$row['language']][] = $row;
        }

        $message = (new \Swift_Message())->setSubject($subject);

        foreach ($languages as $language => $users) {
            //@switch to language in template (@see switch_lang_to and switch_lang_back from Mail class)
            foreach ($users as $user) {
                $message->addBcc($user['email'], $user['name']);
            }
        }

        $message
          ->setBody($this->template->render('mail/text/cat_group_info.text.tpl', $params), 'text/plain')
          ->addPart($this->template->render('mail/html/cat_group_info.html.tpl', $params), 'text/html');

        if (!empty($this->conf['mail_sender_email'])) {
            $from[] = $this->conf['mail_sender_email'];
            if (!empty($this->conf['mail_sender_name'])) {
                $from[] = $this->conf['mail_sender_name'];
            }
        } else {
            $from = [$webmaster['mail_address'], $webmaster['username']];
        }

        $message->setFrom(...$from);
        $message->setReplyTo(...$from);

        $this->mailer->send($message);
    }
}
