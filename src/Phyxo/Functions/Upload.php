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
                'error_message' => 'The original maximum width must be a number between %d and %d',
            ],

            'original_resize_maxheight' => [
                'default' => 2000,
                'min' => 300,
                'max' => 20000,
                'pattern' => '/^\d+$/',
                'can_be_null' => false,
                'error_message' => 'The original maximum height must be a number between %d and %d',
            ],

            'original_resize_quality' => [
                'default' => 95,
                'min' => 50,
                'max' => 98,
                'pattern' => '/^\d+$/',
                'can_be_null' => false,
                'error_message' => 'The original image quality must be a number between %d and %d',
            ],
        ];

        return $upload_form_config;
    }

    public static function save_upload_form_config(array $data, array &$errors = [], array &$form_errors = []): array
    {
        $upload_form_config = self::get_upload_form_config();
        $updates = [];

        foreach ($data as $field => $value) {
            if (!isset($upload_form_config[$field])) {
                continue;
            }
            if (is_bool($upload_form_config[$field]['default'])) {
                $value = isset($value);
                $updates[] = [
                    'param' => $field,
                    'value' => true
                ];
            } elseif ($upload_form_config[$field]['can_be_null'] && empty($value)) {
                $updates[] = [
                    'param' => $field,
                    'value' => null,
                ];
            } else {
                $min = $upload_form_config[$field]['min'];
                $max = $upload_form_config[$field]['max'];
                $pattern = $upload_form_config[$field]['pattern'];

                if (preg_match($pattern, (string) $value) && $value >= $min && $value <= $max) {
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
            return $updates;
        }

        return [];
    }
}
