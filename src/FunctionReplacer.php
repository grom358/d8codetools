<?php
namespace CodeTools;

use Pharborist\Parser;
use Pharborist\TokenNode;
use Pharborist\UseDeclarationListNode;

/**
 * A tool to replace procedural function calls with a static class method.
 * @package CodeTools
 */
class FunctionReplacer {
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
  private $className;

  /**
   * @var string
   */
  private $aliasName;

  /**
   * @var string
   */
  private $classMethodName;

  /**
   * Constructor a FunctionReplacer that can replace calls to procedural
   * functions with call to static class method.
   * @param string $old_function_name Procedural function name to replace
   * @param string $class_path Fully qualified class name
   * @param string $alias_name Alias name for class if conflicting class name exists
   * @param string $class_method_name Name of static method to call instead
   */
  public function __construct($old_function_name, $class_path, $alias_name, $class_method_name) {
    $this->oldFunctionName = $old_function_name;
    $this->classPath = $class_path;
    $this->aliasName = $alias_name;
    $this->classMethodName = $class_method_name;
    $parts = explode('\\', $class_path);
    $this->className = end($parts);
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
    $replaced = FALSE;
    /** @var \Pharborist\NamespaceNode[] $namespaces */
    $namespaces = $tree->filter('\Pharborist\NamespaceNode');
    if (empty($namespaces)) {
      $node = $tree->children[0];
      if ($node instanceof TokenNode) {
        $token = $node->token;
        if ($token->type !== T_OPEN_TAG) {
          throw new ProcessException("File must begin with opening PHP tag");
        }
      }
      else {
        throw new ProcessException("File must begin with opening PHP tag");
      }

      // Look for file comment
      $default_insert_position = 1;
      $force_after = TRUE;
      for ($i = 1, $n = count($tree->children); $i < $n; ++$i) {
        $node = $tree->children[$i];
        if ($node instanceof TokenNode) {
          $token = $node->token;
          if ($token->type === T_DOC_COMMENT) {
            $doc_comment = $token->text;
            if (preg_match("|^/\*\*\n \* @file|", $doc_comment)) {
              $default_insert_position = $i + 1;
              $force_after = FALSE;
            }
            break;
          }
          elseif ($token->type !== T_WHITESPACE && $token->type !== T_COMMENT) {
            // Stop looking if we find token that is neither whitespace or a comment
            break;
          }
        }
        else {
          // Stop looking if we see a statement.
          break;
        }
      }
      $replaced = $this->processNamespace($tree, $default_insert_position, $force_after);
    }
    elseif (count($namespaces) > 1) {
      // Check all namespaces have a body.
      foreach ($namespaces as $namespace) {
        if (!isset($namespace->body)) {
          throw new ProcessException("Namespaces must have a body if more then one namespace in file!");
        }
      }
      // Process each namespace separately.
      foreach ($namespaces as $namespace) {
        $replaced = $this->processNamespace($namespace->body, 0) || $replaced;
      }
    }
    else {
      // Find position in children of the first namespace.
      $i = -1;
      foreach ($tree->children as $i => $node) {
        if ($node === $namespaces[0]) {
          break;
        }
      }
      $replaced = $this->processNamespace($tree, $i + 1);
    }
    if ($replaced) {
      $tree->modified = TRUE;
    }
  }

  /**
   * Find the first node that matches type.
   * @param \Pharborist\Node[] $nodes Array of nodes
   * @param string $type Name of class to match
   * @return bool|int Position of first node, or FALSE if not found
   */
  private function findFirst(array $nodes, $type) {
    foreach ($nodes as $i => $node) {
      if ($node instanceof $type) {
        return $i;
      }
    }
    return FALSE;
  }

  /**
   * Find the last node that matches type.
   * @param \Pharborist\Node[] $nodes Array of nodes
   * @param string $type Name of class to match
   * @return bool|int Position of last node, or FALSE if not found
   */
  private function findLast(array $nodes, $type) {
    if (empty($nodes)) {
      return FALSE;
    }
    for ($i = count($nodes) - 1; $i >= 0; --$i) {
      $node = $nodes[$i];
      if ($node instanceof $type) {
        return $i;
      }
    }
    return FALSE;
  }

  /**
   * Process top level node.
   * @param \Pharborist\Node $top Root node to process
   * @param int $default_insertion_position Position to insert if no use declaration found
   * @param bool $force_after TRUE if forcing newline after when no use declaration found
   * @return bool TRUE if replacements where made
   */
  private function processNamespace(\Pharborist\Node $top, $default_insertion_position, $force_after = FALSE) {
    $before_newline_count = $after_newline_count = 0;
    $insertion_position = $default_insertion_position;
    $alias = $this->className;
    // Check only one block of use declarations
    $start_offset = $this->findFirst($top->children, '\Pharborist\UseDeclarationListNode');
    if ($start_offset !== FALSE) {
      $insertion_position = $this->findUseInsertionPoint(
        $top, $start_offset, $alias, $before_newline_count, $after_newline_count);
    }
    elseif ($force_after) {
      $after_newline_count = 1;
    }
    else {
      $before_newline_count = 2;
    }

    // Find matching function calls.
    /** @var \Pharborist\FunctionCallNode $function_calls */
    $function_calls = $top->find('\Pharborist\FunctionCallNode');
    $matching_function_calls = array();
    foreach ($function_calls as $function_call) {
      if ($function_call->functionReference->children[0] instanceof TokenNode) {
        $name = $function_call->functionReference->children[0]->token;
        if ($name->text == $this->oldFunctionName) {
          $matching_function_calls[] = &$name->text;
        }
      }
    }

    if (empty($matching_function_calls)) {
      return FALSE;
    }

    // Insert use declaration.
    if ($insertion_position !== -1) {
      $snippet = str_repeat("\n", $before_newline_count) .
        'use ' . $this->classPath;
      if ($alias !== $this->className) {
        $snippet .= ' as ' . $this->aliasName;
      }
      $snippet .= ';' . str_repeat("\n", $after_newline_count);
      $insert_nodes = Parser::parseSnippet($snippet)->children;
      array_splice($top->children, $insertion_position, 0, $insert_nodes);
    }

    // Replace the function calls.
    foreach ($matching_function_calls as &$name) {
      $name = $alias . '::' . $this->classMethodName;
    }

    return TRUE;
  }

  /**
   * Find the position to insert use declaration at.
   * @param \Pharborist\Node $top Root node to process
   * @param int $start_offset Position of first use declaration
   * @param string $alias Name of class
   * @param int $before_newline_count Newlines to insert before use declaration
   * @param int $after_newline_count Newlines to insert after use declaration
   * @return int Position to insert use declaration, -1 if use declaration already exists
   * @throws ProcessException If unable to find position to insert
   */
  private function findUseInsertionPoint(\Pharborist\Node $top, $start_offset, &$alias, &$before_newline_count, &$after_newline_count) {
    // $alias_invalid is set to TRUE if unable to use the alias.
    $alias_invalid = FALSE;
    // $uses is array of qualified class names in use declarations.
    $uses = array();
    $end_offset = $this->findLast($top->children, '\Pharborist\UseDeclarationListNode');
    // Check only single block of use declarations.
    for ($i = $start_offset; $i <= $end_offset; ++$i) {
      $node = $top->children[$i];
      if ($node instanceof UseDeclarationListNode) {
        /** @var \Pharborist\UseDeclarationNode $declaration_node */
        foreach ($node->declarations as $declaration_node) {
          $class_path = (string) $declaration_node->namespacePath;
          if ($class_path === $this->classPath) {
            // Already has use declaration for class.
            if (isset($declaration_node->alias)) {
              $alias = $declaration_node->alias;
            }
            return -1;
          }
          else {
            if (isset($declaration_node->alias)) {
              $class_name = (string) $declaration_node->alias;
            }
            else {
              $parts = explode('\\', $class_path);
              $class_name = end($parts);
            }
            $uses[$class_path] = $i;
            if ($class_name === $this->aliasName) {
              $alias_invalid = TRUE;
            }
            if ($class_name === $this->className) {
              $alias = $this->aliasName;
            }
          }
        }
      }
      elseif ($node instanceof TokenNode) {
        $type = $node->token->type;
        if (!in_array($type, array(T_WHITESPACE, T_DOC_COMMENT, T_COMMENT))) {
          throw new ProcessException("Only whitespace & comments are allowed between use declarations!");
        }
      }
      else {
        throw new ProcessException("Only one set of use declarations are allowed!");
      }
    }
    if ($alias_invalid && $this->aliasName === $alias) {
      throw new ProcessException("Unable to insert use declaration!");
    }
    // Find insertion point.
    $paths = array_keys($uses);
    for ($i = 0, $n = count($paths); $i < $n; $i++) {
      $path = $paths[$i];
      if (strnatcasecmp($this->classPath, $path) < 0) {
        break;
      }
    }
    if ($i === 0) {
      $after_newline_count = 1;
      return $start_offset;
    }
    elseif ($i === count($paths)) {
      $before_newline_count = 1;
      return $end_offset + 1;
    }
    else {
      $offset = $uses[$path];
      $before_newline_count = 1;
      return $offset - 1;
    }
  }
}
