<?php
namespace CodeTools;

use Pharborist\Parser;

class CommentFunctionReplacerTest extends \PHPUnit_Framework_TestCase {
  public function testLineComment() {
    $source = <<<'EOF'
<?php
// test_func()
EOF;
    $dest = <<<'EOF'
<?php
// \TestNamespace\Test::testFunc()
EOF;

    $tree = Parser::parseSource($source);
    $replacer = new CommentFunctionReplacer('test_func', 'TestNamespace\Test', 'testFunc');
    $replacer->processTree($tree);
    $this->assertEquals($dest, $tree->getText());
  }
}
