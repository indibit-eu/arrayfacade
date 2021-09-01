<?php
declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
;

/*
 * Available rules: https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/3.0/doc/rules/index.rst
 * Available sets: https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/3.0/doc/ruleSets/index.rst
 */

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PSR12' => true, // PSR12 coding style. https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/3.0/doc/ruleSets/PSR12.rst
    '@DoctrineAnnotation' => true, // Format Doctrine annotations. https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/3.0/doc/ruleSets/DoctrineAnnotation.rst
])
    ->setFinder($finder)
    ;
