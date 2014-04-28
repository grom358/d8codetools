<?php
namespace CodeTools;

use Pharborist\Filter;
use Pharborist\Node;
use Pharborist\TokenNode;

/**
 * Replace old php array syntax array(...) with the new syntax [...]
 */
class ArraySyntaxReplacer {
  /**
   * Process tree replacing old syntax with new syntax.
   * @param \Pharborist\TopNode $tree
   * @return bool
   */
  public function processTree($tree) {
    $modified = FALSE;
    /** @var \Pharborist\ArrayNode $array */
    foreach ($tree->find(Filter::isInstanceOf('\Pharborist\ArrayNode')) as $array) {
      if ($array->firstChild()->getText() === 'array') {
        // Remove any hidden tokens between T_ARRAY and (
        $array->firstChild()->nextUntil(function (Node $node) {
          return $node instanceof TokenNode && $node->getType() === '(';
        })->remove();
        $array->firstChild()->remove(); // remove array
        $array->firstChild()->replaceWith(new TokenNode('[', '[')); // replace ( with [
        $array->lastChild()->replaceWith(new TokenNode(']', ']')); // replace ) with ]
        $modified = TRUE;
      }
    }
    return $modified;
  }
}
