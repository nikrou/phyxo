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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommentNotificationSubscriber implements EventSubscriberInterface
{
    private $router, $mailer, $conf, $userMapper, $translator;

    public function __construct(MailerInterface $mailer, Conf $conf, RouterInterface $router, UserMapper $userMapper, TranslatorInterface $translator)
    {
        $this->mailer = $mailer;
        $this->conf = $conf;
        $this->router = $router;
        $this->userMapper = $userMapper;
        $this->translator = $translator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CommentEvent::class => 'onCommentAction'
        ];
    }

    public function onCommentAction(CommentEvent $event)
    {
        $webmaster = $this->userMapper->getWebmaster();

        $comment = $event->getComment();
        if (!empty($comment['id'])) {
            $params['comment_url'] = $this->router->generate('comment_edit', ['comment_id' => $comment['id']], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        if ($event->getAction() === 'delete') {
            $subject = $this->translator->trans('number_of_comments_deleted', ['count' => count($comment['ids'])], 'admin');
            $comment['IDS'] = implode(',', $comment['ids']);
        } elseif ($event->getAction() === 'edit') {
            $subject = $this->translator->trans('A comment has been edited', [], 'admin');
        } else {
            $subject = $this->translator->trans('Comment by %by%', ['%by%' => $comment['author']], 'admin');
        }

        $params = [
            'GALLERY_TITLE' => $this->conf['gallery_title'],
            'CONTACT_MAIL' => $webmaster['mail_address'],
            'MAIL_TITLE' => $subject,
            'MAIL_THEME' => $this->conf['mail_theme'],
            'comment' => $comment,
            'comment_action' => $event->getAction(),
            'GALLERY_URL' => $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'LEVEL_SEPARATOR' => $this->conf['level_separator'],
        ];

        if (!empty($this->conf['mail_sender_email'])) {
            $from[] = $this->conf['mail_sender_email'];
            if (!empty($this->conf['mail_sender_name'])) {
                $from[] = $this->conf['mail_sender_name'];
            }
        } else {
            $from = [$webmaster['mail_address'], $webmaster['username']];
        }

        $message = (new TemplatedEmail())
            ->subject($subject)
            ->to(...$from)
            ->textTemplate('mail/text/new_comment.text.twig')
            ->htmlTemplate('mail/html/new_comment.html.twig')
            ->context($params);

        $message->from(...$from);
        $message->replyTo(...$from);

        $this->mailer->send($message);
    }
}
