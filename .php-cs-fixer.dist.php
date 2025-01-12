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

// the final goal is to respect all PSR-2 rules.

$header = <<<'EOF'
    This file is part of Phyxo package

    Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
    Licensed under the GPL version 2.0 license.

    For the full copyright and license information, please view the LICENSE
    file that was distributed with this source code.
    EOF;

$finder = PhpCsFixer\Finder::create()->in(
    [
        __DIR__ . '/src',
        __DIR__ . '/tests'
    ]
);

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        '@PSR2' => true,
        '@PHP83Migration' => true,

        'align_multiline_comment' => ['comment_type' => 'all_multiline'],
        'array_indentation' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => ['operators' => ['=>' => 'single_space', '=' => 'single_space']],
        'blank_line_after_opening_tag' => false,
        'blank_lines_before_namespace' => false,
        'braces_position' => [
            'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
        ],
        'cast_spaces' => ['space' => 'single'],
        'class_attributes_separation' => ['elements' => ['const' => 'only_if_meta', 'method' => 'one', 'property' => 'only_if_meta', 'trait_import' => 'none']],
        'class_definition' => ['single_line' => true],
        'concat_space' => ['spacing' => 'one'],
        'constant_case' => true,
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'declare_equal_normalize' => true,
        'encoding' => true,
        'full_opening_tag' => true,
        'header_comment' => ['comment_type' => 'comment', 'header' => $header, 'location' => 'after_open', 'separate' => 'bottom'],
        'heredoc_to_nowdoc' => true,
        'include' => true,
        'indentation_type' => true,
        'lowercase_cast' => true,
        'method_chaining_indentation' => true,
        'no_extra_blank_lines' => ['tokens' => ['extra', 'curly_brace_block']],
        'no_leading_import_slash' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_spaces_around_offset' => true,
        'no_unused_imports' => true,
        'no_whitespace_before_comma_in_array' => true,
        'no_whitespace_in_blank_line' => true,
        'octal_notation' => false,
        'object_operator_without_whitespace' => true,
        'single_line_comment_style' => ['comment_types' => ['hash']],
        'single_space_around_construct' => true,
        'space_after_semicolon' => true,
        'spaces_inside_parentheses' => ['space' => 'none'],
        'statement_indentation' => true,
        'ternary_operator_spaces' => true,
        'trim_array_spaces' => true,
        'trailing_comma_in_multiline' => false,
        'type_declaration_spaces' => ['elements' => ['function', 'property']],
        'whitespace_after_comma_in_array' => true,
        'yoda_style' => false,
    ])
    ->setFinder($finder);
