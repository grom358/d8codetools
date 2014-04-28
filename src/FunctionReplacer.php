<?php
namespace CodeTools;

use Pharborist\DocCommentNode;
use Pharborist\Filter;
use Pharborist\HiddenNode;
use Pharborist\Parser;
use Pharborist\TokenNode;
use Pharborist\WhitespaceNode;

/**
 * A tool to replace procedural function calls with a static class method.
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
   * @param \Pharborist\ParentNode $tree
   * @throws ProcessException
   */
  public function processTree($tree) {
    if ($tree->firstChild() === NULL) {
      return;
    }
    $replaced = FALSE;
    /** @var \Pharborist\NodeCollection $namespaces */
    $namespaces = $tree->children(Filter::isInstanceOf('\Pharborist\NamespaceNode'));
    if ($namespaces->count() === 0) {
      $node = $tree->firstChild();
      if ($node instanceof TokenNode) {
        if ($node->getType() !== T_OPEN_TAG) {
          throw new ProcessException("File must begin with opening PHP tag");
        }
      }
      else {
        throw new ProcessException("File must begin with opening PHP tag");
      }
      $before_newline_count = 1;
      $after_newline_count = 2;

      // Look for file comment
      $insertion_point = $node;
      $child = $node->nextToken();
      while ($child instanceof HiddenNode) {
        if ($child instanceof DocCommentNode) {
          $doc_comment = $child->getText();
          if (preg_match('|^/\*\*\n \* @file|', $doc_comment)) {
            $insertion_point = $child;
            $before_newline_count = 2;
            $after_newline_count = 0;
            break;
          }
        }
        $child = $child->nextToken();
      }
      $replaced = $this->processNamespace($tree, $insertion_point, $before_newline_count, $after_newline_count);
    }
    elseif (count($namespaces) > 1) {
      // Check all namespaces have a body.
      /** @var \Pharborist\NamespaceNode $namespace */
      foreach ($namespaces as $namespace) {
        if ($namespace->getBody() === NULL) {
          throw new ProcessException("Namespaces must have a body if more then one namespace in file!");
        }
      }
      // Process each namespace separately.
      foreach ($namespaces as $namespace) {
        $replaced = $this->processNamespace($namespace->getBody(), $namespace->getBody()->previous()) || $replaced;
      }
    }
    else {
      /** @var \Pharborist\NamespaceNode $namespace */
      $namespace = $namespaces[0];
      $replaced = $this->processNamespace($namespace->getBody(), $namespace->getBody()->previous());
    }
    if ($replaced) {
      $tree->modified = TRUE;
    }
  }

  /**
   * @param \Pharborist\StatementBlockNode $statement_block
   * @param \Pharborist\Node $insert_after
   * @param int $before_newline_count
   * @param int $after_newline_count
   * @return bool
   */
  private function processNamespace($statement_block, $insert_after, $before_newline_count = 0, $after_newline_count = 2) {
    $alias = $this->findUseInsertionPoint($statement_block, $insert_after, $before_newline_count, $after_newline_count);
    $matching_function_calls = $this->findMatchingCalls($statement_block);
    if (empty($matching_function_calls)) {
      return FALSE;
    }
    // Insert use declaration
    if ($insert_after) {
      $snippet = 'use ' . $this->classPath;
      if ($alias !== $this->className) {
        $snippet .= ' as ' . $this->aliasName;
      }
      $snippet .= ';';
      /** @var \Pharborist\UseDeclarationBlockNode $use_block */
      $use_block = Parser::parseSnippet($snippet)->firstChild();
      $declaration_statement = $use_block->firstChild();
      $insert_nodes = [];
      if ($before_newline_count > 0) {
        $insert_nodes[] = new WhitespaceNode(T_WHITESPACE, str_repeat("\n", $before_newline_count), NULL, $before_newline_count);
      }
      $insert_nodes[] = $declaration_statement;
      if ($after_newline_count > 0) {
        $insert_nodes[] = new WhitespaceNode(T_WHITESPACE, str_repeat("\n", $after_newline_count), NULL, $after_newline_count);
      }
      $insert_after->after($insert_nodes);
    }
    $new_function_name = $alias . '::' . $this->classMethodName;
    foreach ($matching_function_calls as $function_call) {
      $function_call->getName()->replaceWith(new TokenNode(T_STRING, $new_function_name, -1, -1));
    }
    return TRUE;
  }

  /**
   * @param \Pharborist\StatementBlockNode $statement_block
   * @param \Pharborist\Node $insert_after
   * @param int $before_newline_count
   * @param int $after_newline_count
   * @return string
   * @throws ProcessException
   */
  private function findUseInsertionPoint($statement_block, &$insert_after, &$before_newline_count, &$after_newline_count) {
    $alias = $this->className;
    // $alias_invalid is set to TRUE if unable to use the alias.
    $alias_invalid = FALSE;
    $find_insertion = TRUE;

    $use_blocks = $statement_block->children(Filter::isInstanceOf('\Pharborist\UseDeclarationBlockNode'));
    if ($use_blocks->count() > 1) {
      throw new ProcessException("Only one block of use declarations is allowed!");
    }
    $use_statements = NULL;
    if ($use_blocks->count() === 0) {
      return $alias;
    }

    /** @var \Pharborist\UseDeclarationBlockNode $use_block */
    $use_block = $use_blocks[0];

    /** @var \Pharborist\UseDeclarationStatementNode[] $use_statements */
    $use_statements = $use_block->getDeclarationStatements();

    // Check only one block of use declarations
    $last_use_statement = $use_statements[count($use_statements) - 1];

    // Find which use declaration to insert after
    foreach ($use_statements as $use_statement) {
      foreach ($use_statement->getDeclarations() as $declaration) {
        $class_path = $declaration->getName()->getText();
        if ($class_path === $this->classPath) {
          // Already has use declaration for class.
          if ($declaration->getAlias()) {
            $alias = $declaration->getAlias()->getText();
          }
          $insert_after = NULL;
          return $alias;
        }
        else {
          if ($declaration->getAlias()) {
            $class_name = $declaration->getAlias()->getText();
          }
          else {
            $parts = explode('\\', $class_path);
            $class_name = end($parts);
          }
          if ($class_name === $this->aliasName) {
            $alias_invalid = TRUE;
          }
          if ($class_name === $this->className) {
            $alias = $this->aliasName;
          }
          if ($find_insertion && strnatcasecmp($this->classPath, $class_path) < 0) {
            $insert_after = $use_statement->firstToken()->previousToken();
            $before_newline_count = 0;
            $after_newline_count = 1;
            $find_insertion = FALSE;
          }
        }
      }
    }

    if ($alias_invalid && $this->aliasName === $alias) {
      throw new ProcessException("Unable to insert use declaration!");
    }

    if ($find_insertion) {
      $insert_after = $last_use_statement;
      $before_newline_count = 1;
      $after_newline_count = 0;
    }

    return $alias;
  }

  /**
   * @param \Pharborist\StatementBlockNode $statement_block
   * @return \Pharborist\FunctionCallNode[]
   */
  private function findMatchingCalls($statement_block) {
    $function_calls = $statement_block->find(Filter::isInstanceOf('\Pharborist\FunctionCallNode'));
    $matching_function_calls = array();
    /** @var \Pharborist\FunctionCallNode $function_call */
    foreach ($function_calls as $function_call) {
      $namespace_path = $function_call->getName()->getText();
      if ($this->oldFunctionName === $namespace_path) {
        $matching_function_calls[] = $function_call;
      }
    }
    return $matching_function_calls;
  }
}
