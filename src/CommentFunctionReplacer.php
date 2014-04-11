<?php
namespace CodeTools;

/**
 * Replace reference to procedural function with static method call.
 */
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
    $this->pattern = '/\b' . $old_function_name . '\(\)/';
    $this->replacement = '\\' . $class_path . '::' . $class_method_name . '()';
  }

  /**
   * Replace function calls in file.
   * @param \Pharborist\ParentNode $tree
   * @throws ProcessException
   */
  public function processTree($tree) {
    if ($tree->getChildCount() == 0) {
      return;
    }

    /** @var \Pharborist\CommentNode[] $comment_nodes */
    $comment_nodes = $tree->find('\Pharborist\CommentNode');
    foreach ($comment_nodes as $comment_node) {
      $text = preg_replace($this->pattern, $this->replacement, $comment_node->getText(), -1, $count);
      if ($count > 0) {
        $comment_node->setText($text);
        $tree->modified = TRUE;
      }
    }
  }
}
