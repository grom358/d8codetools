<?php
namespace CodeTools;

use Pharborist\Parser;

class FunctionReplacerTest extends \PHPUnit_Framework_TestCase {
  public function testNone() {
    $source = <<<'EOF'
<?php
test_func();
EOF;
    $dest = <<<'EOF'
<?php

use TestNamespace\Test;

Test::testFunc();
EOF;

    $tree = Parser::parseSource($source);
    $replacer = new FunctionReplacer('test_func', 'TestNamespace\Test', 'TestAlias', 'testFunc');
    $replacer->processTree($tree);
    $this->assertEquals($dest, $tree->getText());
  }

  public function testFileComment() {
    $source = <<<'EOF'
<?php

/**
 * @file
 */

test_func();
EOF;
    $dest = <<<'EOF'
<?php

/**
 * @file
 */

use TestNamespace\Test;

Test::testFunc();
EOF;

    $tree = Parser::parseSource($source);
    $replacer = new FunctionReplacer('test_func', 'TestNamespace\Test', 'TestAlias', 'testFunc');
    $replacer->processTree($tree);
    $this->assertEquals($dest, $tree->getText());
  }

  public function testNamespace() {
    $source = <<<'EOF'
<?php
namespace UnitTest;

test_func();
EOF;
    $dest = <<<'EOF'
<?php
namespace UnitTest;

use TestNamespace\Test;

Test::testFunc();
EOF;

    $tree = Parser::parseSource($source);
    $replacer = new FunctionReplacer('test_func', 'TestNamespace\Test', 'TestAlias', 'testFunc');
    $replacer->processTree($tree);
    $this->assertEquals($dest, $tree->getText());
  }

  public function testNamespaceAlt() {
    $source = <<<'EOF'
<?php
namespace UnitTest {
test_func();
}
EOF;
    $dest = <<<'EOF'
<?php
namespace UnitTest {
use TestNamespace\Test;

Test::testFunc();
}
EOF;

    $tree = Parser::parseSource($source);
    $replacer = new FunctionReplacer('test_func', 'TestNamespace\Test', 'TestAlias', 'testFunc');
    $replacer->processTree($tree);
    $this->assertEquals($dest, $tree->getText());
  }

  public function testImportBefore() {
    $source = <<<'EOF'
<?php

use Z\MyClass;

test_func();
EOF;
    $dest = <<<'EOF'
<?php

use TestNamespace\Test;
use Z\MyClass;

Test::testFunc();
EOF;

    $tree = Parser::parseSource($source);
    $replacer = new FunctionReplacer('test_func', 'TestNamespace\Test', 'TestAlias', 'testFunc');
    $replacer->processTree($tree);
    $this->assertEquals($dest, $tree->getText());
  }

  public function testImportAfter() {
    $source = <<<'EOF'
<?php

use A\MyClass;

test_func();
EOF;
    $dest = <<<'EOF'
<?php

use A\MyClass;
use TestNamespace\Test;

Test::testFunc();
EOF;

    $tree = Parser::parseSource($source);
    $replacer = new FunctionReplacer('test_func', 'TestNamespace\Test', 'TestAlias', 'testFunc');
    $replacer->processTree($tree);
    $this->assertEquals($dest, $tree->getText());
  }
}
