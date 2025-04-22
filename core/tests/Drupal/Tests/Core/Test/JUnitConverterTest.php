<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Test;

use Drupal\Core\Test\JUnitConverter;
use Drupal\Tests\UnitTestCase;
use Drupal\TestTools\Random;
use org\bovigo\vfs\vfsStream;

/**
 * Tests Drupal\Core\Test\JUnitConverter.
 *
 * This test class has significant overlap with
 * Drupal\Tests\simpletest\Kernel\PhpUnitErrorTest.
 *
 * @coversDefaultClass \Drupal\Core\Test\JUnitConverter
 *
 * @group Test
 * @group simpletest
 *
 * @see \Drupal\Tests\simpletest\Kernel\PhpUnitErrorTest
 */
class JUnitConverterTest extends UnitTestCase {

  /**
   * Tests errors reported.
   *
   * @covers ::xmlToRows
   */
  public function testXmlToRowsWithErrors(): void {
    $phpunit_error_xml = __DIR__ . '/fixtures/phpunit_error.xml';

    $res = JUnitConverter::xmlToRows(1, $phpunit_error_xml);
    $this->assertCount(4, $res, 'All test cases got extracted');
    $this->assertNotEquals('pass', $res[0]['status']);
    $this->assertEquals('fail', $res[0]['status']);

    // Test nested testsuites, which appear when you use @dataProvider.
    for ($i = 0; $i < 3; $i++) {
      $this->assertNotEquals('pass', $res[$i + 1]['status']);
      $this->assertEquals('fail', $res[$i + 1]['status']);
    }

    // Make sure xmlToRows() does not balk if there are no test results.
    $this->assertSame([], JUnitConverter::xmlToRows(1, 'does_not_exist'));
  }

  /**
   * @covers ::xmlToRows
   */
  public function testXmlToRowsEmptyFile(): void {
    // File system with an empty XML file.
    vfsStream::setup('junit_test', NULL, ['empty.xml' => '']);
    $this->assertSame([], JUnitConverter::xmlToRows(23, vfsStream::url('junit_test/empty.xml')));
  }

  /**
   * @covers ::xmlElementToRows
   */
  public function testXmlElementToRows(): void {
    $junit = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="Drupal\Tests\simpletest\Unit\TestDiscoveryTest" file="/Users/paul/projects/drupal/core/modules/simpletest/tests/src/Unit/TestDiscoveryTest.php" tests="3" assertions="5" errors="0" failures="0" skipped="0" time="0.215539">
    <testcase name="testGetTestClasses" class="Drupal\Tests\simpletest\Unit\TestDiscoveryTest" classname="Drupal.Tests.simpletest.Unit.TestDiscoveryTest" file="/Users/paul/projects/drupal/core/modules/simpletest/tests/src/Unit/TestDiscoveryTest.php" line="108" assertions="2" time="0.100787"/>
  </testsuite>
</testsuites>
EOD;
    $simpletest = [
      [
        'test_id' => 23,
        'test_class' => 'Drupal\Tests\simpletest\Unit\TestDiscoveryTest',
        'status' => 'pass',
        'message' => '',
        'message_group' => 'Other',
        'function' => 'Drupal\Tests\simpletest\Unit\TestDiscoveryTest->testGetTestClasses()',
        'line' => 108,
        'file' => '/Users/paul/projects/drupal/core/modules/simpletest/tests/src/Unit/TestDiscoveryTest.php',
      ],
    ];
    $this->assertEquals($simpletest, JUnitConverter::xmlElementToRows(23, new \SimpleXMLElement($junit)));
  }

  /**
   * Tests the conversion of JUnit XML to SimpleTest row.
   *
   * @param string $junitXmlString
   *   The JUnit XML string to test.
   * @param array $expectedSimpletestRow
   *   The expected simple test row.
   *
   * @covers ::convertTestCaseToSimpletestRow
   * @dataProvider simpletestDataProvider
   */
  public function testConvertTestCaseToSimpletestRow(string $junitXmlString, array $expectedSimpletestRow): void {
    $this->assertEquals($expectedSimpletestRow, JUnitConverter::convertTestCaseToSimpletestRow($expectedSimpletestRow['test_id'], new \SimpleXMLElement($junitXmlString)));
    $this->assertLessThanOrEqual(255, strlen($expectedSimpletestRow['function']));
  }

  /**
   * Provides data for testConvertTestCaseToSimpletestRow().
   */
  public static function simpletestDataProvider(): array {
    $long_function_name = Random::machineName(220);
    return [
      [
        <<<EOD
        <testcase name="testGetTestClasses" class="Drupal\Tests\simpletest\Unit\TestDiscoveryTest" classname="Drupal.Tests.simpletest.Unit.TestDiscoveryTest" file="/Users/paul/projects/drupal/core/modules/simpletest/tests/src/Unit/TestDiscoveryTest.php" line="108" assertions="2" time="0.100787"/>
        EOD,
        [
          'test_id' => 23,
          'test_class' => 'Drupal\Tests\simpletest\Unit\TestDiscoveryTest',
          'status' => 'pass',
          'message' => '',
          'message_group' => 'Other',
          'function' => 'Drupal\Tests\simpletest\Unit\TestDiscoveryTest->testGetTestClasses()',
          'line' => 108,
          'file' => '/Users/paul/projects/drupal/core/modules/simpletest/tests/src/Unit/TestDiscoveryTest.php',
        ],
      ],
      [
        <<<EOD
        <testcase name="{$long_function_name}" class="Drupal\Tests\big_pipe\Unit\Render\BigPipeResponseAttachmentsProcessorTest" classname="Drupal.Tests.big_pipe.Unit.Render.BigPipeResponseAttachmentsProcessorTest" file="/Users/paul/projects/drupal/core/modules/big_pipe/tests/src/Unit/Render/BigPipeResponseAttachmentsProcessorTest.php" line="83" assertions="4" time="0.100787"/>
        EOD,
        [
          'test_id' => 24,
          'test_class' => 'Drupal\Tests\big_pipe\Unit\Render\BigPipeResponseAttachmentsProcessorTest',
          'status' => 'pass',
          'message' => '',
          'message_group' => 'Other',
          'function' => mb_substr("Drupal\Tests\big_pipe\Unit\Render\BigPipeResponseAttachmentsProcessorTest->{$long_function_name}()", 0, 255),
          'line' => 83,
          'file' => '/Users/paul/projects/drupal/core/modules/big_pipe/tests/src/Unit/Render/BigPipeResponseAttachmentsProcessorTest.php',
        ],
      ],
    ];

  }

}
