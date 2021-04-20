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

use App\Entity\Image;
use App\Entity\ImageTag;
use App\Entity\Tag;
use App\Entity\User;
use App\Metadata;
use Phyxo\Image\DerivativeImage;
use Phyxo\Conf;
use App\Repository\TagRepository;
use App\Repository\ImageTagRepository;
use App\Repository\UserCacheRepository;
use App\Repository\ImageRepository;
use Phyxo\Functions\Language;
use Phyxo\Image\SrcImage;
use Phyxo\Image\ImageStandardParams;
use Symfony\Component\Routing\RouterInterface;

class TagMapper
{
    private $conf, $image_std_params, $router, $metadata, $userCacheRepository, $imageRepository, $tagRepository, $imageTagRepository;

    public function __construct(Conf $conf, ImageStandardParams $image_std_params, RouterInterface $router, Metadata $metadata, ImageTagRepository $imageTagRepository,
                                UserCacheRepository $userCacheRepository, ImageRepository $imageRepository, TagRepository $tagRepository)
    {
        $this->conf = $conf;
        $this->image_std_params = $image_std_params;
        $this->router = $router;
        $this->metadata = $metadata;
        $this->userCacheRepository = $userCacheRepository;
        $this->imageRepository = $imageRepository;
        $this->tagRepository = $tagRepository;
        $this->imageTagRepository = $imageTagRepository;
    }

    public function getRepository(): TagRepository
    {
        return $this->tagRepository;
    }

    public function alphaCompare(Tag $a, Tag $b)
    {
        return strcmp(Language::transliterate($a->getName()), Language::transliterate($b->getName()));
    }

    public function counterCompare(Tag $a, Tag $b)
    {
        if ($a->getCounter() === $b->getCounter()) {
            return ($a->getId() < $b->getId()) ? -1 : 1;
        }

        return ($a->getCounter() < $b->getCounter()) ? +1 : -1;
    }

    /**
     * Returns all tags even associated to no image.
     * The list can be filtered by $q
     */
    public function getAllTags(string $q = '')
    {
        $tags = [];
        foreach ($this->getRepository()->searchAll($q) as $tag) {
            $tags[] = $tag;
        }

        usort($tags, [$this, 'alphaCompare']);

        return $tags;
    }

    public function getPendingTags()
    {
        $tags = [];
        $params = $this->image_std_params->getByType(ImageStandardParams::IMG_THUMB);
        foreach ($this->imageTagRepository->getPendingTags() as $image_tag) {
            $image = $image_tag->getImage();
            $tags[] = array_merge(
                $image_tag->getTag()->toArray(),
                [
                    'image_id' => $image->getId(),
                    'created_by' => $image_tag->getCreatedBy(),
                    'status' => $image_tag->getStatus(),
                    'thumb_src' => (new DerivativeImage(new SrcImage($image->toArray(), $this->conf['picture_ext']), $params, $this->image_std_params))->getUrl(),
                    'picture_url' => $this->router->generate('admin_photo', ['image_id' => $image->getId()]),
                ]
            );
        }

        usort($tags, '\Phyxo\Functions\Utils::tag_alpha_compare');

        return $tags;
    }

    /**
     * Returns all available tags for the connected user (not sorted).
     * The returned list can be a subset of all existing tags due to permissions,
     * also tags with no images are not returned.
     *
     * @return array [id, name, counter, url_name]
     */
    public function getAvailableTags(User $user)
    {
        $tags = [];
        $available_tags = $this->imageTagRepository->getAvailableTags($user->getId(), $user->getUserInfos()->getForbiddenCategories(), $this->conf['show_pending_added_tags'] ?? false);
        foreach ($available_tags as $row) {
            $tag = $row[0]->getTag();
            if (isset($tags[$tag->getId()])) {
                $tag->setCounter($tags[$tag->getId()]->getCounter() + $row['counter']);
            } else {
                $tag->setCounter($row['counter']);
            }
            $tags[$tag->getId()] = $tag;
        }

        return $tags;
    }

    public function getRelatedTags(User $user, int $image_id, int $max_tags, array $excluded_tag_ids = [])
    {
        $tags = [];
        $related_tags = $this->getRepository()->getRelatedTags(
            $user->getId(), $image_id, $max_tags,
            $this->conf['show_pending_added_tags'] ?? false,
            $this->conf['show_pending_deleted_tags'] ?? false
        );
        foreach ($related_tags as $tag) {
            $image_tag = $tag->getImageTags()->filter(function(ImageTag $it) use ($tag) {
                return $it->getTag()->getId() === $tag->getId();
            })->first();

            $tag->setRelatedImageTagInfos($image_tag);
            $tags[] = $tag;
        }

        usort($tags, [$this, 'alphaCompare']);

        return $tags;
    }

    public function getCommonTags(User $user, array $items, int $max_tags, array $excluded_tag_ids = [])
    {
        if (count($items) === 0) {
            return [];
        }

        $tags = [];
        foreach ($this->getRepository()->getCommonTags($user->getId(), $items, $max_tags, $excluded_tag_ids) as $row) {
            $tag = $row[0];
            $tag->setCounter($row['counter']);

            $image_tag = $tag->getImageTags()->filter(function(ImageTag $it) use ($tag) {
                return $it->getTag()->getId() === $tag->getId();
            })->first();

            $tag->setCounter($image_tag->isValidated());
            $tags[] = $tag;
        }

        usort($tags, [$this, 'alphaCompare']);

        return $tags;
    }

    /**
     * Get tags list and surround ids by ~~, for getTagsIds()) to differenciate new tags from existing tags
     *
     * @param boolean $only_user_language - if true, only local name is returned for
     *    multilingual tags (if ExtendedDescription plugin is active)
     * @return array[] ('id', 'name')
     */
    public function prepareTagsListForUI(array $tags, $only_user_language = true) : array
    {
        $taglist = [];
        $altlist = [];
        foreach ($tags as $tag) {
            $taglist[] = [
                'name' => $tag->getName(),
                'id' => '~~' . $tag->getId() . '~~',
            ];

            if (!$only_user_language) {
                $alt_names = [];

                foreach (array_diff(array_unique($alt_names), [$tag->getName()]) as $alt) {
                    $altlist[] = [
                        'name' => $alt,
                        'id' => '~~' . $tag->getId() . '~~',
                    ];
                }
            }
        }
        usort($taglist, '\Phyxo\Functions\Utils::tag_alpha_compare');
        if (count($altlist)) {
            usort($altlist, '\Phyxo\Functions\Utils::tag_alpha_compare');
            $taglist = array_merge($taglist, $altlist);
        }

        return $taglist;
    }

    /**
     * Get tags ids from a list of raw tags (existing tags or new tags).
     *
     * In $raw_tags we receive something like array('~~6~~', '~~59~~', 'New
     * tag', 'Another new tag') The ~~34~~ means that it is an existing
     * tag. We added the surrounding ~~ to permit creation of tags like "10"
     * or "1234" (numeric characters only)
     *
     * @param string|string[] $raw_tags - array or comma separated string
     * @param boolean $allow_create
     * @return int[]
     */
    public function getTagsIds($raw_tags, bool $allow_create = true)
    {
        $tag_ids = [];
        if (!is_array($raw_tags)) {
            $raw_tags = explode(',', $raw_tags);
        }

        foreach ($raw_tags as $raw_tag) {
            if (preg_match('/^~~(\d+)~~$/', $raw_tag, $matches)) {
                $tag_ids[] = $matches[1];
            } elseif ($allow_create) {
                // we have to create a new tag
                $tag_ids[] = $this->tagIdFromTagName($raw_tag);
            }
        }

        return $tag_ids;
    }

    /**
     * Returns a tag id from its name. If nothing found, create a new tag.
     */
    public function tagIdFromTagName(string $tag_name) : int
    {
        $tag_name = trim($tag_name);

        $tag = $this->getRepository()->findOneBy(['name' => $tag_name]);
        if (is_null($tag)) {
            // search existing by url name
            $tag = $this->getRepository()->findOneBy(['url_name' => $tag_name]);

            if (is_null($tag)) { // finally create the tag
                $tag = new Tag();
                $tag->setName($tag_name);
                $tag->setUrlName($tag_name);
                $tag->setLastModified(new \DateTime());
                $this->getRepository()->addOrUpdateTag($tag);

                $this->invalidateUserCacheNbTags();
            }
        }

        return $tag->getId();
    }

    /**
     * Add new tags to a set of images.
     */
    public function addTags(array $tag_ids, array $image_ids, User $user)
    {
        if (count($tag_ids) === 0 or count($image_ids) === 0) {
            return;
        }

        $tags = [];
        foreach ($this->getRepository()->findBy(['id' => array_unique($tag_ids)]) as $tag) {
            $tags[] = $tag;
        }

        foreach ($this->imageRepository->findBy(['id' => $image_ids]) as $image) {
            foreach ($tags as $tag) {
                $image_tag = new ImageTag();
                $image_tag->setImage($image);
                $image_tag->setTag($tag);
                $image_tag->setCreatedBy($user);
                $image->addImageTag($image_tag);
            }
            $this->imageRepository->addOrUpdateImage($image);
        }

        $this->invalidateUserCacheNbTags();
    }

    /**
     * Set tags to an image.
     * Warning: given tags are all tags associated to the image, not additionnal tags.
     *
     * @param int[] $tags
     * @param int $image_id
     */
    public function setTags(array $tags, int $image_id, User $user)
    {
        $this->setTagsOf([$image_id => $tags], $user);
    }

    /**
     * Delete tags and tags associations.
     *
     * @param int[] $tag_ids
     */
    public function deleteTags(array $tag_ids)
    {
        $this->imageTagRepository->deleteByTagIds($tag_ids);
        $this->getRepository()->deleteTags($tag_ids);

        $this->invalidateUserCacheNbTags();
    }

    /**
     * Set tags of images. Overwrites all existing associations.
     *
     * @param array $tags_of - keys are image ids, values are array of tag ids
     */
    public function setTagsOf(array $tags_of, User $user)
    {
        if (count($tags_of) > 0) {
            $tag_ids = [];
            foreach ($tags_of as $ids) {
                $tag_ids = array_merge($tag_ids, $ids);
            }
            $tag_ids = array_unique($tag_ids);

            $tags = [];
            foreach ($this->getRepository()->findBy(['id' => array_unique($tag_ids)]) as $tag) {
                $tags[] = $tag;
            }

            foreach ($this->imageRepository->findBy(['id' => array_keys($tags_of)]) as $image) {
                $image->getImageTags()->clear();
                $this->imageRepository->addOrUpdateImage($image);
                foreach ($tags as $tag) {
                    $image_tag = new ImageTag();
                    $image_tag->setImage($image);
                    $image_tag->setTag($tag);
                    $image_tag->setCreatedBy($user);
                    $image->addImageTag($image_tag);
                }
                $this->imageRepository->addOrUpdateImage($image);
            }

            $this->invalidateUserCacheNbTags();
        }
    }

    /**
     * Deletes all tags linked to no photo
     */
    public function deleteOrphanTags()
    {
        $orphan_tags = [];
        foreach ($this->getRepository()->getOrphanTags() as $tag) {
            $orphan_tags[] = $tag->getId();
        }

        if (count($orphan_tags) > 0) {
            $this->deleteTags($orphan_tags);
        }
    }

    public function associateTags(array $tag_ids, int $image_id)
    {
        if (count($tag_ids) === 0) {
            return;
        }

        $image = $this->imageRepository->find($image_id);
        foreach ($this->getRepository()->findBy(['id' => array_unique($tag_ids)]) as $tag) {
            $image_tag = new ImageTag();
            $image_tag->setImage($image);
            $image_tag->setTag($tag);
            $image->addImageTag($image_tag);
        }
        $this->imageRepository->addOrUpdateImage($image);
        $this->invalidateUserCacheNbTags();
    }

    // @param $elements is an array of tags indexed by image_id
    public function rejectTags(array $elements)
    {
        if (empty($elements)) {
            return;
        }
        $this->imageTagRepository->deleteImageTags($elements);
    }

    // @param $elements in an array of tags indexed by image_id
    public function validateTags(array $elements)
    {
        if (count($elements) === 0) {
            return;
        }

        $image_id = array_keys($elements)[0];
        foreach ($elements[$image_id] as $tag_id) {
            $this->imageTagRepository->validatedImageTag($image_id, $tag_id);
        }
        $this->imageTagRepository->deleteMarkDeletedAndValidated();
        $this->invalidateUserCacheNbTags();
    }

    public function dissociateTags(array $tag_ids, int $image_id)
    {
        if (count($tag_ids) === 0) {
            return;
        }

        $this->imageTagRepository->deleteByImageAndTags($image_id, $tag_ids);
    }

    /**
     * Mark tags as to be validated for addition or deletion.
     */
    public function toBeValidatedTags(Image $image, array $tags_ids, User $user, int $status, bool $validated = false)
    {
        $existing_ids = [];
        if (!$image->getImageTags()->isEmpty()) {
            foreach ($image->getImageTags() as $image_tag) {
                if (in_array($image_tag->getTag()->getId(), $tags_ids)) {
                    $existing_ids[] = $image_tag->getTag()->getId();
                    $image_tag->setCreatedBy($user);
                    $image_tag->setStatus($status);
                    $image_tag->setValidated($validated);
                }
            }
        }

        $image_tag_to_add = array_diff($tags_ids, $existing_ids);
        foreach ($this->getRepository()->findBy(['id' => $image_tag_to_add]) as $tag) {
            $image_tag = new ImageTag();
            $image_tag->setTag($tag);
            $image_tag->setImage($image);
            $image_tag->setCreatedBy($user);
            $image_tag->setStatus($status);
            $image_tag->setValidated($validated);
            $image->addImageTag($image_tag);
        }

        $this->imageRepository->addOrUpdateImage($image);

        $this->invalidateUserCacheNbTags();
    }

    /**
     * Sync all metadata of a list of images.
     * Metadata are fetched from original files and saved in database.
     */
    public function sync_metadata(array $ids, User $user)
    {
        $now = new \DateTime();

        $tags_of = [];
        foreach ($this->imageRepository->findBy(['id' => $ids]) as $image) {
            $metadata = $this->metadata->getSyncMetadata($image->toArray());

            if (count($metadata) === 0) {
                continue;
            }

            $id = $image->getId();
            foreach (['keywords', 'tags'] as $key) {
                if (isset($metadata[$key])) {
                    if (!isset($tags_of[$id])) {
                        $tags_of[$id] = [];
                    }

                    foreach (explode(',', $metadata[$key]) as $tag_name) {
                        $tags_of[$id][] = $this->tagIdFromTagName($tag_name);
                    }
                }
            }

            $image->setDateMetadataUpdate($now);
            $update_fields = $this->metadata->getSyncMetadataAttributes();
            $image->fromArray(
                array_filter(
                    $metadata,
                    function($m) use ($update_fields) {
                        return in_array($m, $update_fields);
                    }, ARRAY_FILTER_USE_KEY
                )
            );

            $this->imageRepository->addOrUpdateImage($image);
        }

        $this->setTagsOf($tags_of, $user);
    }

    /**
     * Invalidates cached tags counter for all users.
     */
    public function invalidateUserCacheNbTags()
    {
        $this->userCacheRepository->invalidateNumberbAvailableTags();
    }

    /**
     * Giving a set of tags with a counter for each one, calculate the display
     * level of each tag.
     *
     * The level of each tag depends on the average count of tags. This
     * calculation method avoid having very different levels for tags having
     * nearly the same count when set are small.
     *
     * @param array $tags at least [id, counter]
     * @return array [..., level]
     */
    public function addLevelToTags(array $tags = [], int $tags_levels = 5) : array
    {
        if (count($tags) === 0) {
            return $tags;
        }

        $total_count = 0;

        foreach ($tags as $tag) {
            $total_count += $tag->getCounter();
        }

        // average count of available tags will determine the level of each tag
        $tag_average_count = $total_count / count($tags);

        // tag levels threshold calculation: a tag with an average rate must have the middle level.
        $threshold_of_level = [];
        for ($i = 1; $i < $tags_levels; $i++) {
            $threshold_of_level[$i] = 2 * $i * $tag_average_count / $tags_levels;
        }

        // display sorted tags
        foreach ($tags as $tag) {
            $tag->setLevel(1);

            // based on threshold, determine current tag level
            for ($i = $tags_levels - 1; $i >= 1; $i--) {
                if ($tag->getCounter() > $threshold_of_level[$i]) {
                    $tag->setLevel($i + 1);
                    break;
                }
            }
        }

        return $tags;
    }
}
