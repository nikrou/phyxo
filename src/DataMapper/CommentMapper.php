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

use App\Entity\Comment;
use App\Events\CommentEvent;
use Phyxo\Conf;
use App\Repository\CommentRepository;
use App\Repository\ImageRepository;
use App\Repository\UserCacheRepository;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommentMapper
{
    private $conf, $userMapper, $eventDispatcher, $translator, $userRepository, $userCacheRepository, $commentRepository, $imageRepository;

    public function __construct(Conf $conf, UserMapper $userMapper, EventDispatcherInterface $eventDispatcher, TranslatorInterface $translator,
                                UserRepository $userRepository, UserCacheRepository $userCacheRepository, CommentRepository $commentRepository, ImageRepository $imageRepository)
    {
        $this->conf = $conf;
        $this->userMapper = $userMapper;
        $this->eventDispatcher = $eventDispatcher;
        $this->translator = $translator;
        $this->userRepository = $userRepository;
        $this->userCacheRepository = $userCacheRepository;
        $this->commentRepository = $commentRepository;
        $this->imageRepository = $imageRepository;
    }

    public function getRepository(): CommentRepository
    {
        return $this->commentRepository;
    }

    public function getUser()
    {
        return $this->userMapper->getUser();
    }

    /**
     * Does basic check on comment and returns action to perform.
     *
     * @param string $action before check
     * @param array $comment
     * @return string validate, moderate, reject
     */
    public function userCommentCheck($action, $comment)
    {
        if ($action === 'reject') {
            return $action;
        }

        $my_action = $this->conf['comment_spam_reject'] ? 'reject' : 'moderate';

        if ($action == $my_action) {
            return $action;
        }

        // we do here only BASIC spam check (plugins can do more)
        if (!$this->userMapper->isGuest()) {
            return $action;
        }

        $link_count = preg_match_all('/https?:\/\//', $comment['content'], $matches);

        if ((strpos($comment['author'], 'http://') !== false) || (strpos($comment['author'], 'https://') !== false)) {
            $link_count++;
        }

        if ($link_count > $this->conf['comment_spam_max_links']) {
            return $my_action;
        }

        return $action;
    }

    public function createComment(string $content, int $image_id, string $author, int $user_id, array $params = [])
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
            $comment->setDate(new \DateTime());
        }
        $comment->setAnonymousId(isset($params['anonymous_id']) ? md5($params['anonymous_id']) : md5('::1'));
        $comment->setValidated(isset($params['validated']) ? $params['validated'] : true);
        $comment->setWebsiteUrl(isset($params['website_url'])?$params['website_url']:'');
        $comment->setEmail(isset($params['email']) ? $params['email'] : '');
        $comment->setImage($image);

        $this->getRepository()->addOrUpdateComment($comment);
    }

    /**
     * Tries to insert a user comment and returns action to perform.
     *
     * @param array &$comm
     * @param array &$infos output array of error messages
     * @return string validate, moderate, reject
     */
    public function insertUserComment(&$comm, &$infos)
    {
        $infos = [];
        if (!$this->conf['comments_validation'] || $this->userMapper->isAdmin()) {
            $comment_action = 'validate'; //one of validate, moderate, reject
        } else {
            $comment_action = 'moderate'; //one of validate, moderate, reject
        }

        // display author field if the user status is guest
        if ($this->userMapper->isGuest()) {
            if (empty($comm['author'])) {
                if ($this->conf['comments_author_mandatory']) {
                    $infos[] = $this->translator->trans('Username is mandatory');
                    $comment_action = 'reject';
                }
                $comm['author'] = 'guest';
            }
            $comm['author_id'] = $this->userMapper->getDefaultUser()->getId();

            // if a guest try to use the name of an already existing user, he must be rejected
            if ($comm['author'] !== 'guest') {
                if ($this->userRepository->isUserExists($comm['author'])) {
                    $infos[] = $this->translator->trans('This login is already used by another user');
                    $comment_action = 'reject';
                }
            }
        } else {
            $comm['author'] = $this->getUser()->getUsername();
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
                $comm['website_url'] = strip_tags($comm['website_url']);
                if (!preg_match('/^https?/i', $comm['website_url'])) {
                    $comm['website_url'] = 'http://' . $comm['website_url'];
                }
                if (!\Phyxo\Functions\Utils::url_check_format($comm['website_url'])) {
                    $infos[] = $this->translator->trans('Your website URL is invalid');
                    $comment_action = 'reject';
                }
            }
        }

        // email
        if (empty($comm['email'])) {
            if (!empty($this->getUser()->getMailAddress())) {
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
            $anti_flood_date = new \DateTime();
            $anti_flood_date->sub(new \DateInterval(sprintf('PT%dS', $this->conf['anti-flood-time'])));

            if ($this->getRepository()->doestAuthorPostMessageAfterThan($comm['author_id'], $anti_flood_date, !$this->userMapper->isGuest() ? $anonymous_id : md5('::1'))) {
                $infos[] = $this->translator->trans('Anti-flood system : please wait for a moment before trying to post another comment');
                $comment_action = 'reject';
            }
        }

        if ($comment_action !== 'reject') {
            $comm['id'] = $this->createComment($comm['content'], $comm['image_id'], $comm['author'], $comm['author_id'], array_merge($comm, [
                'date' => new \DateTime(),
                'validated' => $comment_action === 'validate',

            ]));

            $this->invalidateUserCacheNbComments();
            if (($this->conf['email_admin_on_comment'] && 'validate' == $comment_action)
                || ($this->conf['email_admin_on_comment_validation'] && 'moderate' == $comment_action)) {
                $this->eventDispatcher->dispatch(new CommentEvent($comm, $comment_action));
            }
        }

        return $comment_action;
    }

    /**
     * Tries to delete a (or more) user comment.
     *    only admin can delete all comments
     *    other users can delete their own comments
     */
    public function deleteUserComment(array $comment_ids)
    {
        $this->getRepository()->deleteByIds($comment_ids, !$this->userMapper->isAdmin() ? $this->getUser()->getId() : null);
        $this->invalidateUserCacheNbComments();

        // $this->eventDispatcher->dispatch(new CommentEvent(['ids' => $comment_ids, 'author' => $this->getUser()->getUsername()], 'delete'));
    }

    /**
     * Tries to update a user comment
     *    only admin can update all comments
     *    users can edit their own comments if admin allow them
     *
     * @param array $comment
     * @param string $post_key secret key sent back to the browser
     * @return string validate, moderate, reject
     */
    public function updateUserComment(array $comment_infos, $post_key)
    {
        $comment_action = 'validate';

        if (!$this->conf['comments_validation'] || $this->userMapper->isAdmin()) { // should the updated comment must be validated
            $comment_action = 'validate'; //one of validate, moderate, reject
        } else {
            $comment_action = 'moderate'; //one of validate, moderate, reject
        }

        // website
        if (!empty($comment_infos['website_url'])) {
            $comment_infos['website_url'] = strip_tags($comment_infos['website_url']);
            if (!preg_match('/^https?/i', $comment_infos['website_url'])) {
                $comment['website_url'] = 'http://' . $comment_infos['website_url'];
            }
            if (!\Phyxo\Functions\Utils::url_check_format($comment_infos['website_url'])) {
                //$page['errors'][] = $this->translator->trans('Your website URL is invalid');
                $comment_action = 'reject';
            }
        }

        if ($comment_action !== 'reject') {
            $comment = $this->getRepository()->find($comment_infos['id']);
            $comment->setContent($comment_infos['content']);
            $comment->setValidated($comment_action === 'validate');
            $comment->setWebsiteUrl(!empty($comment_infos['website_url']) ? $comment_infos['website_url'] : '');
            $comment->setDate(new \DateTime());
            $this->getRepository()->addOrUpdateComment($comment);

            // mail admin and ask to validate the comment
            if ($this->conf['email_admin_on_comment_validation'] && $comment_action === 'moderate') {
                $this->eventDispatcher->dispatch(new CommentEvent($comment_infos, $comment_action));
            } else {
                $this->eventDispatcher->dispatch(new CommentEvent(['author' => $this->getUser()->getUsername(), 'content' => $comment_infos['content']], 'edit'));
            }
        }

        return $comment_action;
    }

    /**
     * Tries to validate a user comment.
     */
    public function validateUserComment(array $comment_ids)
    {
        $this->getRepository()->validateUserComment($comment_ids);
        $this->invalidateUserCacheNbComments();
    }

    /**
     * Clears cache of nb comments for all users
     */
    private function invalidateUserCacheNbComments()
    {
        $this->userCacheRepository->invalidateNumberAvailableComments();
    }
}
