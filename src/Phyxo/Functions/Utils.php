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

use App\Entity\Album;
use App\Entity\Group;
use App\Entity\Image;
use App\Entity\Tag;
use App\Entity\UserInfos;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

class Utils
{
    public static function phyxoInstalled(string $config_file): bool
    {
        return is_readable($config_file);
    }

    /**
     * @param array{id: string, url_name: string} $tag
     */
    public static function tagToUrl(array $tag, string $tag_url_style = 'id-tag'): string
    {
        $url_tag = $tag['id'];

        if (($tag_url_style === 'id-tag') && !empty($tag['url_name'])) {
            $url_tag .= '-' . $tag['url_name'];
        }

        return $url_tag;
    }

    /**
     * returns the part of the string after the last ".".
     */
    public static function getExtension(string $filename): string
    {
        return substr(strrchr($filename, '.'), 1, strlen($filename));
    }

    /**
     * returns the part of the string before the last ".".
     * get_filename_wo_extension( 'test.tar.gz' ) = 'test.tar'.
     */
    public static function getFilenameWithoutExtension(string $filename): string
    {
        $pos = strrpos($filename, '.');

        return ($pos === false) ? $filename : substr($filename, 0, $pos);
    }

    /**
     * returns the element name from its filename.
     * removes file extension and replace underscores by spaces.
     */
    public static function getNameFromFile(string $filename): string
    {
        return str_replace('_', ' ', self::getFilenameWithoutExtension($filename));
    }

    /**
     * converts a string from a character set to another character set.
     */
    public static function convertCharset(string $str, string $source_charset, string $dest_charset): string
    {
        if ($source_charset === $dest_charset) {
            return $str;
        }

        if ($source_charset === 'iso-8859-1' && $dest_charset === 'utf-8') {
            return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
        }

        if ($source_charset === 'utf-8' && $dest_charset === 'iso-8859-1') {
            return mb_convert_encoding($str, 'ISO-8859-1');
        }

        if (function_exists('iconv')) {
            return iconv($source_charset, $dest_charset, $str);
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($str, $dest_charset, $source_charset);
        }

        return $str; // TODO
    }

    /**
     * @TODO: use Enum
     *
     * get localized privacy level values
     *
     * @param array<string, string> $available_permission_levels
     *
     * @return array<string, string>
     */
    public static function getPrivacyLevelOptions(TranslatorInterface $translator, array $available_permission_levels = [], string $domain = 'messages'): array
    {
        $options = [];
        $label = '';
        foreach (array_reverse($available_permission_levels) as $level) {
            if (0 == $level) {
                $label = $translator->trans('Everybody', [], $domain);
            } else {
                if (strlen($label) !== 0) {
                    $label .= ', ';
                }

                $label .= $translator->trans('Level ' . $level, [], $domain);
            }

            $options[$level] = $label;
        }

        return $options;
    }

    /**
     * return the branch from the version. For example version 2.2.4 is for branch 2.2.
     */
    public static function getBranchFromVersion(string $version): string
    {
        return implode('.', array_slice(explode('.', $version), 0, 2));
    }

    /**
     * Callback used for sorting by global_rank.
     *
     * @param array{global_rank: string} $a
     * @param array{global_rank: string} $b
     */
    public static function globalRankCompare(array $a, array $b): int
    {
        return strnatcasecmp($a['global_rank'], $b['global_rank']);
    }

    /**
     * Callback used for sorting by rank.
     *
     * @param array{rank: int} $a
     * @param array{rank: int} $b
     */
    public static function rankCompare(array $a, array $b): int
    {
        return $a['rank'] - $b['rank'];
    }

    /**
     * Callback used for sorting by name.
     *
     * @param array{name: string} $a
     * @param array{name: string} $b
     */
    public static function nameCompare(array $a, array $b): int
    {
        return strcmp(strtolower($a['name']), strtolower($b['name']));
    }

    /**
     * Callback used for sorting by name (slug) with cache.
     *
     * @param array{name: string} $a
     * @param array{name: string} $b
     */
    public static function tagAlphaCompare(array $a, array $b): int
    {
        return strcmp(Language::transliterate($a['name']), Language::transliterate($b['name']));
    }

    /**
     * @param array{counter: int, id:int} $a
     * @param array{counter: int, id:int} $b
     */
    public static function counterCompare(array $a, array $b): int
    {
        if ($a['counter'] === $b['counter']) {
            return self::idCompare($a, $b);
        }

        return ($a['counter'] < $b['counter']) ? +1 : -1;
    }

    /**
     * @param array{id: int} $a
     * @param array{id: int} $b
     */
    public static function idCompare(array $a, array $b): int
    {
        return ($a['id'] < $b['id']) ? -1 : 1;
    }

    /**
     * Returns display name for an element.
     * Returns 'name' if exists of name from 'file'.
     *
     * @param array{name: string, file: string} $info
     */
    public static function renderElementName(array $info): string
    {
        if (!empty($info['name'])) {
            return $info['name'];
        }

        return self::getNameFromFile($info['file']);
    }

    /**
     * Returns display description for an element.
     *
     * @param array{comment:string} $info
     */
    public static function renderElementDescription(array $info, string $param = ''): string
    {
        if (!empty($info['comment'])) {
            return $info['comment'];
        }

        return '';
    }

    /**
     * Returns the argument_ids array with new sequenced keys based on related
     * names. Sequence is not case sensitive.
     * Warning: By definition, this function breaks original keys.
     *
     * @param array<int, int> $element_ids
     * @param array<string>   $name
     *
     * @return array<string, int>
     */
    public static function orderByName(array $element_ids, array $name): array
    {
        $ordered_element_ids = [];
        foreach ($element_ids as $k_id => $element_id) {
            $key = strtolower((string) $name[$element_id]) . '-' . $name[$element_id] . '-' . $k_id;
            $ordered_element_ids[$key] = $element_id;
        }

        ksort($ordered_element_ids);

        return $ordered_element_ids;
    }

    /**
     * Returns keys to identify the state of main tables. A key consists of the
     * last modification timestamp and the total of items (separated by a _).
     * Additionally returns the hash of root path.
     * Used to invalidate LocalStorage cache on admin pages.
     * list of keys to retrieve (categories,groups,images,tags,users).
     *
     * @param array<string> $requested
     *
     * @return array<string, string>
     */
    public static function getAdminClientCacheKeys(ManagerRegistry $managerRegistry, array $requested = [], string $base_url = ''): array
    {
        $tables = [
            'categories' => Album::class,
            'images' => Image::class,
            'users' => UserInfos::class,
            'groups' => Group::class,
            'tags' => Tag::class,
        ];

        $returned = $requested === [] ? array_keys($tables) : array_intersect($requested, array_keys($tables));

        $keys = [
            '_hash' => md5($base_url),
        ];

        foreach ($returned as $repository) {
            if (isset($tables[$repository])) {
                $tableInfos = $managerRegistry->getRepository($tables[$repository])->getMaxLastModified();
                $max = (new DateTime($tableInfos['max'] ?? 'now'))->getTimestamp();
                $keys[$repository] = sprintf('%s_%s', $max, $tableInfos['count']);
            }
        }

        return $keys;
    }

    public static function needResize(string $image_filepath, int $max_width, int $max_height): bool
    {
        // TODO : the resize check should take the orientation into account. If a
        // rotation must be applied to the resized photo, then we should test
        // invert width and height.
        $file_infos = self::imageInfos($image_filepath);

        return $file_infos['width'] > $max_width || $file_infos['height'] > $max_height;
    }

    /**
     * @return array{width:int, height:int, filesize:float}
     */
    public static function imageInfos(string $path): array
    {
        [$width, $height] = getimagesize($path);
        $filesize = floor(filesize($path) / 1024);

        return [
            'width' => $width,
            'height' => $height,
            'filesize' => $filesize,
        ];
    }
}
