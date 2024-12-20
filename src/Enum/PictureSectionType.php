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

namespace App\Enum;

enum PictureSectionType: string
{
    case ALBUMS = 'albums';
    case ALBUM = 'album';
    case LIST = 'list';
    case TAGS = 'tags';
    case SEARCH = 'search';
    case FAVORITES = 'favorites';
    case MOST_VISITED = 'most_visited';
    case BEST_RATED = 'best_rated';
    case RECENT_PICS = 'recent_pics';
    case RECENT_ALBUMS = 'recent_cats';
    case FILE = 'file';
    case FROM_CALENDAR = 'from_calendar';

    case CALENDAR_ALBUMS = 'calendar_albums';
}
