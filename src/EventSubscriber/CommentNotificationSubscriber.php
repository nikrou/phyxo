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
use App\Events\CommentEvent;
use Phyxo\Conf;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommentNotificationSubscriber implements EventSubscriberInterface
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
            CommentEvent::class => 'onCommentAction'
        ];
    }

    public function onCommentAction(CommentEvent $event)
    {
        $params = [];
        $from = [];
        $webmaster = $this->userMapper->getWebmaster();

        $comment = $event->getComment();
        if (!empty($comment['id'])) {
            $params['comment_url'] = $this->router->generate('comment_edit', ['comment_id' => $comment['id']], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        if ($event->getAction() === 'delete') {
            $subject = $this->translator->trans('number_of_comments_deleted', ['count' => is_countable($comment['ids']) ? count($comment['ids']) : 0], 'admin');
            $comment['IDS'] = implode(',', $comment['ids']);
        } elseif ($event->getAction() === 'edit') {
            $subject = $this->translator->trans('A comment has been edited', [], 'admin');
        } else {
            $subject = $this->translator->trans('Comment by %by%', ['%by%' => $comment['author']], 'admin');
        }

        $params = [
            'GALLERY_TITLE' => $this->conf['gallery_title'],
            'CONTACT_MAIL' => $webmaster->getMailAddress(),
            'MAIL_TITLE' => $subject,
            'MAIL_THEME' => $this->conf['mail_theme'],
            'comment' => $comment,
            'comment_action' => $event->getAction(),
            'GALLERY_URL' => $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        if (!empty($this->conf['mail_sender_email'])) {
            $from['email'] = $this->conf['mail_sender_email'];
            if (!empty($this->conf['mail_sender_name'])) {
                $from['name'] = $this->conf['mail_sender_name'];
            }
        } else {
            $from = ['email' => $webmaster->getMailAddress(), 'name' => $webmaster->getUsername()];
        }

        $message = (new TemplatedEmail())
            ->subject($subject)
            ->to(new Address($from['email'], $from['name']))
            ->textTemplate('mail/text/new_comment.text.twig')
            ->htmlTemplate('mail/html/new_comment.html.twig')
            ->context($params);

        $message->from(new Address($from['email'], $from['name']));
        $message->replyTo(new Address($from['email'], $from['name']));

        $this->mailer->send($message);
    }
}
