#!/usr/bin/php
<?php
/**
 * @file
 * Command line tool to upgrade array syntax.
 */
require_once 'vendor/autoload.php';

$replacer = new \CodeTools\ArraySyntaxReplacer();

$callback = function ($filename) use ($replacer) {
  try {
    $tree = \Pharborist\Parser::parseFile($filename);
    $modified = $replacer->processTree($tree);
    if ($modified) {
      file_put_contents($filename, $tree->getText());
    }
  } catch (\Pharborist\ParserException $e) {
    die($filename . ': ' . $e->getMessage() . PHP_EOL);
  }
};

\CodeTools\FileUtil::processDrupalPhp('.', $callback);
