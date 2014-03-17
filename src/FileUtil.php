<?php
namespace CodeTools;


class FileUtil {
  /**
   * Recursively search directory for files matching one of the file extensions
   * and call callback function with filename with the match.
   * @param string $dir Directory to recursively search
   * @param array $extensions Array of file extensions to match
   * @param callable $callback Function to callback with matching filename
   */
  public static function findFilesWithExtensions($dir, $extensions, $callback) {
    $directory = new \RecursiveDirectoryIterator($dir);
    $iterator = new \RecursiveIteratorIterator($directory);
    $pattern = '/^.+\.(' . implode('|', $extensions) . ')$/i';
    $regex = new \RegexIterator($iterator, $pattern, \RecursiveRegexIterator::GET_MATCH);
    foreach ($regex as $name => $object) {
      $callback($name);
    }
  }

  /**
   * Recursively search a drupal directory replacing procedural function calls
   * with call to class method.
   * @param string $directory Directory to search
   * @param string $old_function_name Procedural function name to replace
   * @param string $class_path Fully qualified class name
   * @param string $alias_name Alias name for class if conflicting class name exists
   * @param string $class_method_name Name of static method to call instead
   */
  public static function replaceDrupalFunction(
    $directory, $old_function_name, $class_path, $alias_name, $class_method_name) {
    $extensions = array('php', 'inc', 'module', 'install');
    $function_replacer = new FunctionReplacer($old_function_name, $class_path, $alias_name, $class_method_name);
    $callback = array($function_replacer, 'cmdProcessFile');
    self::findFilesWithExtensions($directory, $extensions, $callback);
  }
}
