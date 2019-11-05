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
use App\Security\UserProvider;
use Phyxo\Conf;
use Phyxo\Functions\Language;
use Phyxo\Template\AdminTemplate;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class CommentNotificationSubscriber implements EventSubscriberInterface
{
    private $router, $mailer, $conf, $template, $userProvider, $phyxoVersion, $phyxoWebsite, $userMapper;

    public function __construct(\Swift_Mailer $mailer, Conf $conf, RouterInterface $router, AdminTemplate $template, UserProvider $userProvider, string $phyxoVersion,
                                string $phyxoWebsite, UserMapper $userMapper)
    {
        $this->mailer = $mailer;
        $this->conf = $conf;
        $this->router = $router;
        $this->template = $template;
        $this->userProvider = $userProvider;
        $this->phyxoVersion = $phyxoVersion;
        $this->phyxoWebsite = $phyxoWebsite;
        $this->userMapper = $userMapper;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CommentEvent::class => 'onCommentAction'
        ];
    }

    public function onCommentAction(CommentEvent $event)
    {
        $user = $this->userProvider->loadUserByUsername('guest');

        // @TODO : mail need to be in user's language (@see switch_lang_to and switch_lang_back from Mail class)

        $language_load = Language::load_language(
            'common.lang',
            __DIR__ . '/../../',
            ['language' => $user->getLanguage(), 'return_vars' => true]
        );

        $webmaster = $this->userMapper->getWebmaster();

        $this->template->setLang($language_load['lang']);
        $this->template->setLangInfo($language_load['lang_info']);

        $comment = $event->getComment();
        if (!empty($comment['id'])) {
            $params['comment_url'] = $this->router->generate('comment_edit', ['comment_id' => $comment['id']], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        if ($event->getAction() === 'delete') {
            $subject = Language::l10n_dec('One comment has been deleted', '%d comments have been deleted', count($comment['ids']));
            $comment['IDS'] = implode(',', $comment['ids']);
        } elseif ($event->getAction() === 'edit') {
            $subject = Language::l10n('A comment has been edited');
        } else {
            $subject = Language::l10n_args(Language::get_l10n_args('Comment by %s', $comment['author']));
        }

        $params = [
            'GALLERY_TITLE' => $this->conf['gallery_title'],
            'CONTACT_MAIL' => $webmaster['mail_address'],
            'MAIL_TITLE' => $subject,
            'MAIL_THEME' => $this->conf['mail_theme'],
            'comment' => $comment,
            'comment_action' => $event->getAction(),
            'GALLERY_URL' => $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'lang_info' => $language_load['lang_info'],
            'LEVEL_SEPARATOR' => $this->conf['level_separator'],
            'CONTENT_ENCODING' => 'utf-8',
            'PHYXO_VERSION' => $this->conf['show_version'] ? $this->phyxoVersion : '',
            'PHYXO_URL' => $this->phyxoWebsite,
        ];

        $message = (new \Swift_Message())
            ->setSubject($subject)
            ->addTo('nikrou77@gmail.com')
            ->setBody($this->template->render('mail/text/new_comment.text.tpl', $params), 'text/plain')
            ->addPart($this->template->render('mail/html/new_comment.html.tpl', $params), 'text/html');

        $message->setFrom('nikrou77@gmail.com');
        $message->setReplyTo('nikrou77@gmail.com');

        $this->mailer->send($message);
    }
}
