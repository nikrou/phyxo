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
use App\Events\ActivationKeyEvent;
use Phyxo\Conf;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResetPasswordLinkSubscriber implements EventSubscriberInterface
{
    private $mailer, $userMapper, $conf, $router, $translator;

    public function __construct(MailerInterface $mailer, UserMapper $userMapper, Conf $conf, RouterInterface $router, TranslatorInterface $translator)
    {
        $this->mailer = $mailer;
        $this->userMapper = $userMapper;
        $this->conf = $conf;
        $this->router = $router;
        $this->translator = $translator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ActivationKeyEvent::class => 'onActivationKeyNotifiy'
        ];
    }

    public function onActivationKeyNotifiy(ActivationKeyEvent $event)
    {
        $webmaster = $this->userMapper->getWebmaster();
        $webmaster_address = new Address($webmaster->getMailAddress(), $webmaster->getUserIdentifier());

        $subject = '[' . $this->conf['gallery_title'] . '] ' . $this->translator->trans('Password Reset');

        $params = [
            'GALLERY_TITLE' => $this->conf['gallery_title'],
            'user' => $event->getUser(),
            'url' => $this->router->generate('reset_password', ['activation_key' => $event->getActivationKey()], UrlGeneratorInterface::ABSOLUTE_URL),
            'CONTACT_MAIL' => $webmaster->getMailAddress(),
            'GALLERY_URL' => $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'MAIL_TITLE' => $subject,
            'MAIL_THEME' => $this->conf['mail_theme'],
            'LEVEL_SEPARATOR' => $this->conf['level_separator'],
            'CONTENT_ENCODING' => 'utf-8',
        ];

        $message = (new TemplatedEmail())
            ->subject($subject)
            ->to(new Address($event->getUser()->getMailAddress(), $event->getUser()->getUserIdentifier()))
            ->textTemplate('mail/text/reset_password.text.twig')
            ->htmlTemplate('mail/html/reset_password.html.twig')
            ->context($params);

        $message->from($webmaster_address);
        $message->replyTo($webmaster_address);

        $this->mailer->send($message);
    }
}
