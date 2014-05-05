<?php
namespace CodeTools;

use Pharborist\FunctionCallNode;
use Pharborist\Node;
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
    if ($tree->firstChild() === NULL) {
      return;
    }

    $callback_string = "array('\\" . $this->classPath . "', '" . $this->classMethodName . "')";

    // Find matching function calls.
    $old_function_name = $this->oldFunctionName;
    /** @var \Pharborist\NodeCollection $function_calls */
    $function_calls = $tree->find(function (Node $node) use ($old_function_name, $callback_string) {
      if ($node instanceof FunctionCallNode) {
        $name = $node->getName()->getText();
        if ($name === 'array_map') {
          $arguments = $node->getArguments();
          $callback = $arguments[0];
          $callback_name = trim($callback->getText(), '\'"');
          if ($callback_name === $old_function_name) {
            /** @var $callback TokenNode */
            $callback->setText($callback_string);
            return TRUE;
          }
        }
      }
      return FALSE;
    });

    if ($function_calls->count() > 0) {
      $tree->modified = TRUE;
    }
  }
}
