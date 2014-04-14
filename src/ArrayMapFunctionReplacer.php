<?php
namespace CodeTools;

use Pharborist\StringNode;
use Pharborist\TokenNode;

/**
 * A tool to replace procedural function callbacks to array_map with static class method.
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
   * @param \Pharborist\StatementBlockNode $tree
   * @throws ProcessException
   */
  public function processTree($tree) {
    if ($tree->getFirst() === NULL) {
      return;
    }

    $callback_string = "array('\\" . $this->classPath . "', '" . $this->classMethodName . "')";

    // Find matching function calls.
    /** @var \Pharborist\FunctionCallNode[] $function_calls */
    $function_calls = $tree->find('\Pharborist\FunctionCallNode');
    $matching_function_calls = array();
    foreach ($function_calls as $function_call) {
      if ('array_map' === (string) $function_call->getNamespacePath() && $function_call->getArguments()[0] instanceof StringNode) {
        /** @var \Pharborist\StringNode $callback_arg */
        $callback_arg = $function_call->getArguments()[0];
        $callback_function_name = trim((string) $callback_arg, '\'"');
        if ($callback_function_name === $this->oldFunctionName) {
          $callback_arg->setText($callback_string);
          $tree->modified = TRUE;
        }
      }

    }
  }
}
