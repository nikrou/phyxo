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

namespace App\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class HistorySectionType extends Type
{
    const HISTORY_SECTION = 'history_section';
    const SECTION_CATEGORIES = 'categories';
    const SECTION_TAGS = 'tags';
    const SECTION_SEARCH = 'search';
    const SECTION_LIST = 'list';
    const SECTION_FAVORITES = 'favorites';
    const SECTION_MOST_VISITED = 'most_visited';
    const SECTION_BEST_RATED = 'best_rated';
    const SECTION_RECENT_PICS = 'recent_pics';
    const SECTION_RECENT_CATS = 'recent_cats';

    public function getName()
    {
        return self::HISTORY_SECTION;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return "ENUM('categories','tags','search','list','favorites','most_visited','best_rated','recent_pics','recent_cats')";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!in_array($value, [self::SECTION_CATEGORIES, self::SECTION_TAGS, self::SECTION_SEARCH, self::SECTION_LIST, self::SECTION_FAVORITES,
            self::SECTION_MOST_VISITED, self::SECTION_BEST_RATED, self::SECTION_RECENT_PICS, self::SECTION_RECENT_CATS])) {
            throw new \InvalidArgumentException("Invalid section");
        }

        return $value;
    }
}
