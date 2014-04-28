<?php
namespace CodeTools;

use Pharborist\Parser;

class ArrayMapFunctionReplacerTest extends \PHPUnit_Framework_TestCase {
  public function testArrayMap() {
    $source = <<<'EOF'
<?php
function test() {
  return array_map('test_func', $input);
}
EOF;
    $dest = <<<'EOF'
<?php
function test() {
  return array_map(array('\TestNamespace\Test', 'testFunc'), $input);
}
EOF;

    $tree = Parser::parseSource($source);
    $replacer = new ArrayMapFunctionReplacer('test_func', 'TestNamespace\Test', 'testFunc');
    $replacer->processTree($tree);
    $this->assertEquals($dest, $tree->getText());
  }
}
