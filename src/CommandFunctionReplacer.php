<?php
namespace CodeTools;

use Pharborist\Parser;

class CommandFunctionReplacer {
  private $functionReplacer;
  private $arrayMapFunctionReplacer;
  private $commentFunctionReplacer;

  /**
   * Constructor a FunctionReplacer that can replace calls to procedural
   * functions with call to static class method.
   * @param string $old_function_name Procedural function name to replace
   * @param string $class_path Fully qualified class name
   * @param string $alias_name Alias name for class if conflicting class name exists
   * @param string $class_method_name Name of static method to call instead
   */
  public function __construct($old_function_name, $class_path, $alias_name, $class_method_name) {
    $this->functionReplacer = new FunctionReplacer($old_function_name, $class_path, $alias_name, $class_method_name);
    $this->arrayMapFunctionReplacer = new ArrayMapFunctionReplacer($old_function_name, $class_path, $class_method_name);
    $this->commentFunctionReplacer = new CommentFunctionReplacer($old_function_name, $class_path, $class_method_name);
  }

  /**
   * Wrapper for command line usage. Prints out any error message to standard output.
   * @param string $filename Filename to process
   */
  public function cmdProcessFile($filename) {
    try {
      $tree = Parser::parseFile($filename);
      $this->functionReplacer->processTree($tree);
      $this->arrayMapFunctionReplacer->processTree($tree);
       $this->commentFunctionReplacer->processTree($tree);
      if ($tree->modified) {
        file_put_contents($filename, (string) $tree);
      }
    } catch (ProcessException $e) {
      echo $filename . ': ' . $e->getMessage() . PHP_EOL;
    } catch (\Pharborist\ParserException $e) {
      die($filename . ': ' . $e->getMessage() . PHP_EOL);
    }
  }
}
