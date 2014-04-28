<?php
namespace CodeTools;

use Pharborist\Parser;

class ArraySyntaxReplacerTest extends \PHPUnit_Framework_TestCase {
  public function testArray() {
    $source = <<<'EOF'
<?php
$old = array(4, 2);
$ws = array  (4, 2);
$new = [4, 2];
EOF;
    $dest = <<<'EOF'
<?php
$old = [4, 2];
$ws = [4, 2];
$new = [4, 2];
EOF;

    $tree = Parser::parseSource($source);
    $replacer = new ArraySyntaxReplacer();
    $replacer->processTree($tree);
    $this->assertEquals($dest, $tree->getText());
  }
}
