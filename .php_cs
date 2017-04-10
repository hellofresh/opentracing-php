<?php
$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->exclude('var/cache')
    ->in(__DIR__);

return PhpCsFixer\Config::create()
    ->setRules([
        '@PSR2' => true,
        'declare_strict_types' => true,
        'no_short_echo_tag' => true,
        'no_unused_imports' => true,
        'cast_spaces' => true,
        'no_extra_consecutive_blank_lines' => true,
        'function_typehint_space' => true,
        'return_type_declaration' => ['space_before' => 'one'],
        'include' => true,
        'lowercase_cast' => true,
        'trailing_comma_in_multiline_array' => true,
        'new_with_braces' => true,
        'phpdoc_scalar' => true,
        'phpdoc_types' => true,
        'no_leading_import_slash' => true,
        'blank_line_before_return' => true,
        'short_scalar_cast' => true,
        'single_blank_line_before_namespace' => true,
        'single_quote' => true,
        'ordered_imports' => true,
        'phpdoc_order' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_useless_return' => true,
        'no_useless_else' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
