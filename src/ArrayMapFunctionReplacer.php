<?php
namespace CodeTools;

use Pharborist\Parser;
use Pharborist\TokenNode;

/**
 * A tool to replace procedural function callbacks to array_map with static class method.
 * @package CodeTools
 */
class ArrayMapFunctionReplacer {
  /**
   * @var string
   */
  private $oldFunctionName;

  /**
   * @var string
   */
  private $classPath;

  /**
   * @var string
   */
  private $classMethodName;

  /**
   * Constructor a ArrayMapFunctionReplacer that can replace calls to procedural
   * function callbacks to array_map with call to static class method.
   * @param string $old_function_name Procedural function name to replace
   * @param string $class_path Fully qualified class name
   * @param string $class_method_name Name of static method to call instead
   */
  public function __construct($old_function_name, $class_path, $class_method_name) {
    $this->oldFunctionName = $old_function_name;
    $this->classPath = $class_path;
    $this->classMethodName = $class_method_name;
  }

  /**
   * Replace function calls in file.
   * @param \Pharborist\Node $tree
   * @throws ProcessException
   */
  public function processTree($tree) {
    if (empty($tree->children)) {
      return;
    }

    // Find matching function calls.
    /** @var \Pharborist\FunctionCallNode $function_calls */
    $function_calls = $tree->find('\Pharborist\FunctionCallNode');
    $matching_function_calls = array();
    foreach ($function_calls as $function_call) {
      if ($function_call->functionReference->children[0] instanceof TokenNode) {
        $name = $function_call->functionReference->children[0]->token;
        if ($name->text == 'array_map' && $function_call->arguments[0] instanceof TokenNode) {
          $callback_function_name = trim($function_call->arguments[0], '\'"');
          if ($callback_function_name === $this->oldFunctionName) {
            $matching_function_calls[] = &$function_call->arguments[0]->token->text;
          }
        }
      }
    }

    foreach ($matching_function_calls as &$function_call) {
      $function_call = "array('\\" . $this->classPath . "', '" . $this->classMethodName . "')";
    }

    if (!empty($matching_function_calls)) {
      $tree->modified = TRUE;
    }
  }
}
