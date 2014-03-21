<?php
namespace CodeTools;

class CommentFunctionReplacer {
  /**
   * @var string
   */
  private $pattern;

  /**
   * @var string
   */
  private $replacement;

  /**
   * Constructor a ArrayMapFunctionReplacer that can replace calls to procedural
   * function callbacks to array_map with call to static class method.
   * @param string $old_function_name Procedural function name to replace
   * @param string $class_path Fully qualified class name
   * @param string $class_method_name Name of static method to call instead
   */
  public function __construct($old_function_name, $class_path, $class_method_name) {
    $this->pattern = "/\b{$old_function_name}\(\)/";
    $parts = explode('\\', $class_path);
    $class_name = end($parts);
    //$this->replacement = '\\' . $class_path . '::' . $class_method_name . '()';
    $this->replacement = "$class_name::$class_method_name()";
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

    /** @var \Pharborist\TokenNode $token_nodes */

    $token_nodes = $tree->find('\Pharborist\TokenNode');
    foreach ($token_nodes as $token_node) {
      if ($token_node->token->type === T_COMMENT || $token_node->token->type === T_DOC_COMMENT) {
        $text = &$token_node->token->text;
        $text = preg_replace($this->pattern, $this->replacement, $text, -1, $count);
        if ($count > 0) {
          $tree->modified = TRUE;
        }
      }
    }
  }
}
