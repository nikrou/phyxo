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

use GuzzleHttp\Client;
use App\Repository\ImageRepository;
use App\Repository\ConfigRepository;

class Upload
{
    public static function get_upload_form_config()
    {
        // default configuration for upload
        $upload_form_config = [
            'original_resize' => [
                'default' => false,
                'can_be_null' => false,
            ],

            'original_resize_maxwidth' => [
                'default' => 2000,
                'min' => 500,
                'max' => 20000,
                'pattern' => '/^\d+$/',
                'can_be_null' => false,
                'error_message' => \Phyxo\Functions\Language::l10n('The original maximum width must be a number between %d and %d'),
            ],

            'original_resize_maxheight' => [
                'default' => 2000,
                'min' => 300,
                'max' => 20000,
                'pattern' => '/^\d+$/',
                'can_be_null' => false,
                'error_message' => \Phyxo\Functions\Language::l10n('The original maximum height must be a number between %d and %d'),
            ],

            'original_resize_quality' => [
                'default' => 95,
                'min' => 50,
                'max' => 98,
                'pattern' => '/^\d+$/',
                'can_be_null' => false,
                'error_message' => \Phyxo\Functions\Language::l10n('The original image quality must be a number between %d and %d'),
            ],
        ];

        return $upload_form_config;
    }

    public static function save_upload_form_config($data, &$errors = [], &$form_errors = [])
    {
        global $conn;

        if (!is_array($data) or empty($data)) {
            return false;
        }

        $upload_form_config = self::get_upload_form_config();
        $updates = [];

        foreach ($data as $field => $value) {
            if (!isset($upload_form_config[$field])) {
                continue;
            }
            if (is_bool($upload_form_config[$field]['default'])) {
                if (isset($value)) {
                    $value = true;
                } else {
                    $value = false;
                }

                $updates[] = [
                    'param' => $field,
                    'value' => $conn->boolean_to_string($value)
                ];
            } elseif ($upload_form_config[$field]['can_be_null'] and empty($value)) {
                $updates[] = [
                    'param' => $field,
                    'value' => 'false'
                ];
            } else {
                $min = $upload_form_config[$field]['min'];
                $max = $upload_form_config[$field]['max'];
                $pattern = $upload_form_config[$field]['pattern'];

                if (preg_match($pattern, $value) and $value >= $min and $value <= $max) {
                    $updates[] = [
                        'param' => $field,
                        'value' => $value
                    ];
                } else {
                    $errors[] = sprintf(
                        $upload_form_config[$field]['error_message'],
                        $min,
                        $max
                    );

                    $form_errors[$field] = '[' . $min . ' .. ' . $max . ']';
                }
            }
        }

        if (count($errors) == 0) {
            (new ConfigRepository($conn))->massUpdates(
                [
                    'primary' => ['param'],
                    'update' => ['value']
                ],
                $updates
            );
            return true;
        }

        return false;
    }

    public static function add_uploaded_file($source_filepath, $original_filename = null, $categories = null, $level = null, $image_id = null, $original_md5sum = null)
    {
        global $conn, $conf, $user;

        // 1) move uploaded file to upload/2010/01/22/20100122003814-449ada00.jpg
        //
        // 2) keep/resize original
        //
        // 3) register in database

        // TODO
        // * check md5sum (already exists?)
        if (isset($original_md5sum)) {
            $md5sum = $original_md5sum;
        } else {
            $md5sum = md5_file($source_filepath);
        }

        $file_path = null;
        $is_tiff = false;

        if (isset($image_id)) { // this photo already exists, we update it
            $result = (new ImageRepository($conn))->findByField('id', $image_id);
            while ($row = $conn->db_fetch_assoc($result)) {
                $file_path = $row['path'];
            }

            if (!isset($file_path)) {
                throw new \Exception('[' . __FUNCTION__ . '] this photo does not exist in the database');
            }

            // delete all physical files related to the photo (thumbnail, web site, HD)
            \Phyxo\Functions\Utils::delete_element_files([$image_id]);
        } else {
            // this photo is new current date. @TODO: really need a query for that ?
            list($dbnow) = $conn->db_fetch_row($conn->db_query('SELECT NOW();'));
            list($year, $month, $day) = preg_split('/[^\d]/', $dbnow, 4);

            // upload directory hierarchy
            $upload_dir = sprintf(
                PHPWG_ROOT_PATH . $conf['upload_dir'] . '/%s/%s/%s',
                $year,
                $month,
                $day
            );

            // compute file path
            $date_string = preg_replace('/[^\d]/', '', $dbnow);
            $random_string = substr($md5sum, 0, 8);
            $filename_wo_ext = $date_string . '-' . $random_string;
            $file_path = $upload_dir . '/' . $filename_wo_ext . '.';

            list($width, $height, $type) = getimagesize($source_filepath);

            if (IMAGETYPE_PNG == $type) {
                $file_path .= 'png';
            } elseif (IMAGETYPE_GIF == $type) {
                $file_path .= 'gif';
            } elseif (IMAGETYPE_TIFF_MM == $type or IMAGETYPE_TIFF_II == $type) {
                $is_tiff = true;
                $file_path .= 'tif';
            } elseif (IMAGETYPE_JPEG == $type) {
                $file_path .= 'jpg';
            } elseif (isset($conf['upload_form_all_types']) and $conf['upload_form_all_types']) {
                $original_extension = strtolower(\Phyxo\Functions\Utils::get_extension($original_filename));

                if (in_array($original_extension, $conf['file_ext'])) {
                    $file_path .= $original_extension;
                } else {
                    throw new \Exception('unexpected file type');
                }
            } else {
                throw new \Exception('forbidden file type');
            }

            self::prepare_directory($upload_dir);
        }

        if (is_uploaded_file($source_filepath)) {
            move_uploaded_file($source_filepath, $file_path);
        } else {
            rename($source_filepath, $file_path);
        }
        @chmod($file_path, 0644);

        if ($is_tiff && \Phyxo\Image\Image::get_library() === 'ExtImagick') {
            // move the uploaded file to pwg_representative sub-directory
            $representative_file_path = dirname($file_path) . '/pwg_representative/';
            $representative_file_path .= \Phyxo\Functions\Utils::get_filename_wo_extension(basename($file_path)) . '.';

            $representative_ext = $conf['tiff_representative_ext'];
            $representative_file_path .= $representative_ext;

            self::prepare_directory(dirname($representative_file_path));

            $exec = $conf['ext_imagick_dir'] . 'convert';

            if ('jpg' == $conf['tiff_representative_ext']) {
                $exec .= ' -quality 98';
            }

            $exec .= ' "' . realpath($file_path) . '"';

            $dest = pathinfo($representative_file_path);
            $exec .= ' "' . realpath($dest['dirname']) . '/' . $dest['basename'] . '"';

            $exec .= ' 2>&1';
            @exec($exec, $returnarray);

            // sometimes ImageMagick creates file-0.jpg (full size) + file-1.jpg
            // (thumbnail). I don't know how to avoid it.
            $representative_file_abspath = realpath($dest['dirname']) . '/' . $dest['basename'];
            if (!file_exists($representative_file_abspath)) {
                $first_file_abspath = preg_replace(
                    '/\.' . $representative_ext . '$/',
                    '-0.' . $representative_ext,
                    $representative_file_abspath
                );

                if (file_exists($first_file_abspath)) {
                    rename($first_file_abspath, $representative_file_abspath);
                }
            }
        }

        // generate pwg_representative in case of video
        $ffmpeg_video_exts = [ // extensions tested with FFmpeg
            'wmv', 'mov', 'mkv', 'mp4', 'mpg', 'flv', 'asf', 'xvid', 'divx', 'mpeg',
            'avi', 'rm',
        ];

        if (isset($original_extension) and in_array($original_extension, $ffmpeg_video_exts)) {
            $representative_file_path = dirname($file_path) . '/pwg_representative/';
            $representative_file_path .= \Phyxo\Functions\Utils::get_filename_wo_extension(basename($file_path)) . '.';

            $representative_ext = 'jpg';
            $representative_file_path .= $representative_ext;

            self::prepare_directory(dirname($representative_file_path));

            $second = 1;

            $ffmpeg = $conf['ffmpeg_dir'] . 'ffmpeg';
            $ffmpeg .= ' -i "' . $file_path . '"';
            $ffmpeg .= ' -an -ss ' . $second;
            $ffmpeg .= ' -t 1 -r 1 -y -vcodec mjpeg -f mjpeg';
            $ffmpeg .= ' "' . $representative_file_path . '"';
            @exec($ffmpeg);

            if (!file_exists($representative_file_path)) {
                $representative_ext = null;
            }
        }

        if (isset($original_extension) and 'pdf' == $original_extension && \Phyxo\Image\Image::get_library() === 'ExtImagick') {
            $representative_file_path = dirname($file_path) . '/pwg_representative/';
            $representative_file_path .= \Phyxo\Functions\Utils::get_filename_wo_extension(basename($file_path)) . '.';

            $representative_ext = 'jpg';
            $representative_file_path .= $representative_ext;

            self::prepare_directory(dirname($representative_file_path));

            $exec = $conf['ext_imagick_dir'] . 'convert';
            $exec .= ' -quality 98';
            $exec .= ' "' . realpath($file_path) . '"[0]';

            $dest = pathinfo($representative_file_path);
            $exec .= ' "' . realpath($dest['dirname']) . '/' . $dest['basename'] . '"';
            $exec .= ' 2>&1';
            @exec($exec, $returnarray);
        }

        if (\Phyxo\Image\Image::get_library() !== 'GD') {
            if ($conf['original_resize']) {
                if (self::need_resize($file_path, $conf['original_resize_maxwidth'], $conf['original_resize_maxheight'])) {
                    $img = new \Phyxo\Image\Image($file_path);

                    $img->pwg_resize(
                        $file_path,
                        $conf['original_resize_maxwidth'],
                        $conf['original_resize_maxheight'],
                        $conf['original_resize_quality'],
                        $conf['upload_form_automatic_rotation'],
                        false
                    );

                    $img->destroy();
                }
            }
        }

        // we need to save the rotation angle in the database to compute
        // width/height of "multisizes"
        $rotation_angle = \Phyxo\Image\Image::get_rotation_angle($file_path);
        $rotation = \Phyxo\Image\Image::get_rotation_code_from_angle($rotation_angle);

        $file_infos = self::image_infos($file_path);

        if (isset($image_id)) {
            $update = [
                'file' => $conn->db_real_escape_string(isset($original_filename) ? $original_filename : basename($file_path)),
                'filesize' => $file_infos['filesize'],
                'width' => $file_infos['width'],
                'height' => $file_infos['height'],
                'md5sum' => $md5sum,
                'added_by' => $user['id'],
                'rotation' => $rotation,
            ];

            if (isset($level)) {
                $update['level'] = $level;
            }

            (new ImageRepository($conn))->updateImage($update, $image_id);
        } else {
            // database registration
            $file = $conn->db_real_escape_string(isset($original_filename) ? $original_filename : basename($file_path));
            $insert = [
                'file' => $file,
                'name' => \Phyxo\Functions\Utils::get_name_from_file($file),
                'date_available' => $dbnow,
                'path' => preg_replace('#^' . preg_quote(PHPWG_ROOT_PATH) . '#', '', $file_path),
                'filesize' => $file_infos['filesize'],
                'width' => $file_infos['width'],
                'height' => $file_infos['height'],
                'md5sum' => $md5sum,
                'added_by' => $user['id'],
                'rotation' => $rotation,
            ];

            if (isset($level)) {
                $insert['level'] = $level;
            }

            if (isset($representative_ext)) {
                $insert['representative_ext'] = $representative_ext;
            }

            $image_id = (new ImageRepository($conn))->addImage($insert);
        }

        if (isset($categories) and count($categories) > 0) {
            \Phyxo\Functions\Category::associate_images_to_categories(
                [$image_id],
                $categories
            );
        }

        // update metadata from the uploaded file (exif/iptc)
        if ($conf['use_exif'] and !function_exists('exif_read_data')) {
            $conf['use_exif'] = false;
        }
        \Phyxo\Functions\Metadata::sync_metadata([$image_id]);
        \Phyxo\Functions\Utils::invalidate_user_cache();

        // cache thumbnail
        $result = (new ImageRepository($conn))->findByField('id', $image_id);
        $image_infos = $conn->db_fetch_assoc($result);

        \Phyxo\Functions\URL::set_make_full_url();
        // in case we are on uploadify.php, we have to replace the false path
        $thumb_url = preg_replace('#admin/include/i#', 'i', \Phyxo\Image\DerivativeImage::thumb_url($image_infos));
        \Phyxo\Functions\URL::unset_make_full_url();

        $client = new Client(['http_errors' => false]);
        $response = $client->request('GET', $thumb_url);

        return $image_id;
    }

    // @TODO: move in a more generic class
    public static function prepare_directory($directory)
    {
        if (!is_dir($directory)) {
            if (substr(PHP_OS, 0, 3) == 'WIN') {
                $directory = str_replace('/', DIRECTORY_SEPARATOR, $directory);
            }
            umask(0000);
            $recursive = true;
            if (!@mkdir($directory, 0777, $recursive)) {
                throw new \Exception('[prepare_directory] cannot create directory "' . $directory . '"');
            }
        }

        if (!is_writable($directory)) {
            // last chance to make the directory writable
            @chmod($directory, 0777);

            if (!is_writable($directory)) {
                throw new \Exception('[prepare_directory] directory "' . $directory . '" has no write access');
            }
        }
    }

    public static function need_resize($image_filepath, $max_width, $max_height)
    {
        // TODO : the resize check should take the orientation into account. If a
        // rotation must be applied to the resized photo, then we should test
        // invert width and height.
        list($width, $height) = getimagesize($image_filepath);

        if ($width > $max_width or $height > $max_height) {
            return true;
        }

        return false;
    }

    public static function image_infos($path)
    {
        list($width, $height) = getimagesize($path);
        $filesize = floor(filesize($path) / 1024);

        return [
            'width' => $width,
            'height' => $height,
            'filesize' => $filesize,
        ];
    }

    public static function is_valid_image_extension($extension)
    {
        global $conf;

        if (isset($conf['upload_form_all_types']) and $conf['upload_form_all_types']) {
            $extensions = $conf['file_ext'];
        } else {
            $extensions = $conf['picture_ext'];
        }

        return array_unique(array_map('strtolower', $extensions));
    }

    // not used ?
    public static function file_upload_error_message($error_code)
    {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return sprintf(
                    \Phyxo\Functions\Language::l10n('The uploaded file exceeds the upload_max_filesize directive in php.ini: %sB'),
                    self::get_ini_size('upload_max_filesize', false)
                );
            case UPLOAD_ERR_FORM_SIZE:
                return \Phyxo\Functions\Language::l10n('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form');
            case UPLOAD_ERR_PARTIAL:
                return \Phyxo\Functions\Language::l10n('The uploaded file was only partially uploaded');
            case UPLOAD_ERR_NO_FILE:
                return \Phyxo\Functions\Language::l10n('No file was uploaded');
            case UPLOAD_ERR_NO_TMP_DIR:
                return \Phyxo\Functions\Language::l10n('Missing a temporary folder');
            case UPLOAD_ERR_CANT_WRITE:
                return \Phyxo\Functions\Language::l10n('Failed to write file to disk');
            case UPLOAD_ERR_EXTENSION:
                return \Phyxo\Functions\Language::l10n('File upload stopped by extension');
            default:
                return \Phyxo\Functions\Language::l10n('Unknown upload error');
        }
    }

    public static function get_ini_size($ini_key, $in_bytes = true)
    {
        $size = ini_get($ini_key);

        if ($in_bytes) {
            $size = self::convert_shorthand_notation_to_bytes($size);
        }

        return $size;
    }

    public static function convert_shorthand_notation_to_bytes($value)
    {
        $suffix = substr($value, -1);
        $multiply_by = null;

        if ('K' == $suffix) {
            $multiply_by = 1024;
        } elseif ('M' == $suffix) {
            $multiply_by = 1024 * 1024;
        } elseif ('G' == $suffix) {
            $multiply_by = 1024 * 1024 * 1024;
        }

        if (isset($multiply_by)) {
            $value = substr($value, 0, -1);
            $value *= $multiply_by;
        }

        return $value;
    }

    // not used ?
    public static function add_upload_error($upload_id, $error_message)
    {
        $_SESSION['uploads_error'][$upload_id][] = $error_message;
    }

    public static function ready_for_upload_message()
    {
        global $conf;

        $relative_dir = preg_replace('#^' . realpath(PHPWG_ROOT_PATH) . '#', '', $conf['upload_dir']);
        $absolute_dir = realpath(PHPWG_ROOT_PATH) . '/' . $conf['upload_dir'];

        if (!is_dir($absolute_dir)) {
            if (!is_writable(dirname($absolute_dir))) {
                return sprintf(
                    \Phyxo\Functions\Language::l10n('Create the "%s" directory at the root of your Phyxo installation'),
                    $relative_dir
                );
            }
        } else {
            if (!is_writable($absolute_dir)) {
                @chmod($absolute_dir, 0777);

                if (!is_writable($absolute_dir)) {
                    return sprintf(
                        \Phyxo\Functions\Language::l10n('Give write access (chmod 777) to "%s" directory at the root of your Phyxo installation'),
                        $relative_dir
                    );
                }
            }
        }

        return null;
    }
}
