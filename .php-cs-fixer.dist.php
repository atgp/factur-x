<?php

$finder = (new PhpCsFixer\Finder())
    ->in(['src/', 'tests/'])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'combine_consecutive_unsets' => true,
        'no_superfluous_phpdoc_tags' => false,
        'phpdoc_separation' => false,
        'phpdoc_types_order' => false,
        'native_function_invocation' => false,
        'single_line_throw' => false,
        'heredoc_to_nowdoc' => true,
        'no_extra_blank_lines' => ['tokens' => [
            'break', 'continue', 'extra', 'return', 'throw', 'use',
            'parenthesis_brace_block', 'square_brace_block', 'curly_brace_block',
        ]],
        'no_unreachable_default_argument_value' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'nullable_type_declaration_for_default_null_value' => false,
        'ordered_class_elements' => true,
        'ordered_imports' => true,
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_order' => true,
        'psr_autoloading' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
