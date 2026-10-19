<?php

$psConfig = new PrestaShop\CodingStandards\CsFixer\Config();

$rules = array_merge(
    $psConfig->getRules(),
    [
        // PrestaShop canonical header layout: the license docblock must sit
        // directly under the opening tag (no blank line before the file comment),
        // which conflicts with @Symfony's blank_line_after_opening_tag.
        'blank_line_after_opening_tag' => false,
    ]
);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['vendor', 'dist']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setRules($rules)
    ->setFinder($finder);
