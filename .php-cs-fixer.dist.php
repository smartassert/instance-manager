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
        '@PER-CS' => true,
        '@PhpCsFixer' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'declare_strict_types' => true,
        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,
    ])
    ->setFinder($finder)
    ;
