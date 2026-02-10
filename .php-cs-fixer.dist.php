<?php

$exclusions = [
    __DIR__ . '/src/Kernel.php',
    __DIR__ . '/tests/bootstrap.php',
];

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->filter(function (\Symfony\Component\Finder\SplFileInfo $file) use ($exclusions) {
        return !in_array($file->getPathname(), $exclusions);
    })
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'trailing_comma_in_multiline' => false,
        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,
        'declare_strict_types' => true,
        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'continue',
                'declare',
                'default',
                'phpdoc',
                'do',
                'exit',
                'for',
                'goto',
                'include',
                'include_once',
                'require',
                'require_once',
                'return',
                'switch',
                'throw',
                'try',
                'while',
                'yield',
                'yield_from',
            ],
        ],
        // Following configuration added to allow CI builds to pass
        // @todo remove in #519
        'single_line_empty_body' => false,
        'string_implicit_backslashes' => false,
        'operator_linebreak' => false,
    ])
    ->setFinder($finder)
    ;
