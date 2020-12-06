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

namespace Phyxo\Functions;

class Tag
{
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
    public static function addLevelToTags(array $tags = [], int $tags_levels = 5) : array
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

        // tag levels threshold calculation: a tag with an average rate must have
        // the middle level.
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
