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

namespace App\DataMapper;

use DateTime;
use DateInterval;
use App\Entity\Comment;
use App\Entity\User;
use App\Events\CommentEvent;
use Phyxo\Conf;
use App\Repository\CommentRepository;
use App\Repository\ImageRepository;
use App\Repository\UserCacheRepository;
use App\Repository\UserRepository;
use App\Security\AppUserService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommentMapper
{
    public function __construct(
        private Conf $conf,
        private readonly UserMapper $userMapper,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $userRepository,
        private readonly UserCacheRepository $userCacheRepository,
        private readonly CommentRepository $commentRepository,
        private readonly ImageRepository $imageRepository,
        private readonly AppUserService $appUserService
    ) {
    }

    public function getRepository(): CommentRepository
    {
        return $this->commentRepository;
    }

    public function getUser(): ?User
    {
        return $this->appUserService->getUser();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function createComment(string $content, int $image_id, string $author, int $user_id, array $params = []): int
    {
        $image = $this->imageRepository->find($image_id);
        $user = $this->userMapper->getRepository()->find($user_id);
        $comment = new Comment();
        $comment->setContent($content);
        $comment->setAuthor($author);
        $comment->setUser($user);
        if (isset($params['date'])) {
            $comment->setDate($params['date']);
        } else {
            $comment->setDate(new DateTime());
        }
        $comment->setAnonymousId(isset($params['anonymous_id']) ? md5((string) $params['anonymous_id']) : md5('::1'));
        $comment->setValidated($params['validated'] ?? true);
        $comment->setWebsiteUrl($params['website_url'] ?? '');
        $comment->setEmail($params['email'] ?? '');
        $comment->setImage($image);

        return $this->getRepository()->addOrUpdateComment($comment);
    }

    /**
     * Tries to insert a user comment and returns action to perform.
     * return string validate, moderate, reject
     *
     * @param array<string, mixed> $comm
     * @param array<string> $infos
     */
    public function insertUserComment(array &$comm, array &$infos): string
    {
        $infos = [];
        if (!$this->conf['comments_validation'] || $this->userMapper->isAdmin()) {
            $comment_action = 'validate'; //one of validate, moderate, reject
        } else {
            $comment_action = 'moderate'; //one of validate, moderate, reject
        }

        // display author field if the user status is guest
        if ($this->appUserService->isGuest()) {
            if (empty($comm['author'])) {
                if ($this->conf['comments_author_mandatory']) {
                    $infos[] = $this->translator->trans('Username is mandatory');
                    $comment_action = 'reject';
                }
                $comm['author'] = 'guest';
            }
            $comm['author_id'] = $this->userMapper->getDefaultUser()->getId();

            // if a guest try to use the name of an already existing user, he must be rejected
            if ($comm['author'] !== 'guest' && $this->userRepository->isUserExists($comm['author'])) {
                $infos[] = $this->translator->trans('This login is already used by another user');
                $comment_action = 'reject';
            }
        } else {
            $comm['author'] = $this->getUser()->getUserIdentifier();
            $comm['author_id'] = $this->getUser()->getId();
        }

        if (empty($comm['content'])) { // empty comment content
            $comment_action = 'reject';
        }

        // website
        if (!empty($comm['website_url'])) {
            if (!$this->conf['comments_enable_website']) { // honeypot: if the field is disabled, it should be empty !
                $comment_action = 'reject';
            } else {
                $comm['website_url'] = strip_tags((string) $comm['website_url']);
                if (!preg_match('/^https?/i', $comm['website_url'])) {
                    $comm['website_url'] = 'http://' . $comm['website_url'];
                }
                if (!filter_var($comm['website_url'], FILTER_VALIDATE_URL)) {
                    $infos[] = $this->translator->trans('Your website URL is invalid');
                    $comment_action = 'reject';
                }
            }
        }

        // email
        if (empty($comm['email'])) {
            if (!in_array($this->getUser()->getMailAddress(), [null, '', '0'], true)) {
                $comm['email'] = $this->getUser()->getMailAddress();
            } elseif ($this->conf['comments_email_mandatory']) {
                $infos[] = $this->translator->trans('Email address is missing. Please specify an email address.');
                $comment_action = 'reject';
            }
        } elseif (filter_var($comm['email'], FILTER_VALIDATE_EMAIL) !== false) {
            $infos[] = $this->translator->trans('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
            $comment_action = 'reject';
        }

        $anonymous_id = $comm['ip'];
        if ($comment_action !== 'reject' && $this->conf['anti-flood_time'] > 0 && !$this->userMapper->isAdmin()) { // anti-flood system
            $anti_flood_date = new DateTime();
            $anti_flood_date->sub(new DateInterval(sprintf('PT%dS', $this->conf['anti-flood-time'])));

            if ($this->getRepository()->doestAuthorPostMessageAfterThan($comm['author_id'], $anti_flood_date, $this->appUserService->isGuest() ? md5('::1') : $anonymous_id)) {
                $infos[] = $this->translator->trans('Anti-flood system : please wait for a moment before trying to post another comment');
                $comment_action = 'reject';
            }
        }

        if ($comment_action !== 'reject') {
            $comm['id'] = $this->createComment($comm['content'], $comm['image_id'], $comm['author'], $comm['author_id'], array_merge($comm, [
                'date' => new DateTime(),
                'validated' => $comment_action === 'validate',

            ]));

            $this->userCacheRepository->invalidateNumberAvailableComments();
            if (($this->conf['email_admin_on_comment'] && 'validate' === $comment_action)
                || ($this->conf['email_admin_on_comment_validation'] && 'moderate' === $comment_action)) {
                $this->eventDispatcher->dispatch(new CommentEvent($comm, $comment_action));
            }
        }

        return $comment_action;
    }
}
