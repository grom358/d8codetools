#!/usr/bin/php
<?php
/**
 * @file
 * Command line launcher for FileUtil::replaceDrupalFunction.
 */
require_once 'vendor/autoload.php';

//@todo check command line arguments
if (count($argv) != 5) {
  die('Usage: ' . basename(__FILE__) . ' [function_name] [qualified_class_name] [alias] [static_method]' . PHP_EOL);
}

$old_function_name = $argv[1];
$class_path = $argv[2];
$alias = $argv[3];
$class_method_name = $argv[4];

\CodeTools\FileUtil::replaceDrupalFunction('.', $old_function_name, $class_path, $alias, $class_method_name);
