<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Utility;

use Drupal\Core\Utility\Error;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Utility\Error.
 */
#[CoversClass(Error::class)]
#[Group('Utility')]
class ErrorTest extends UnitTestCase {

  /**
   * Tests the getLastCaller() method.
   *
   * @param array $backtrace
   *   The test backtrace array.
   * @param array $expected
   *   The expected return array.
   */
  #[DataProvider('providerTestGetLastCaller')]
  public function testGetLastCaller(array $backtrace, array $expected): void {
    $this->assertSame($expected, Error::getLastCaller($backtrace));
  }

  /**
   * Data provider for testGetLastCaller.
   *
   * @return array
   *   An array of parameter data.
   */
  public static function providerTestGetLastCaller(): array {
    $data = [];

    // Test with just one item. This should default to the function being
    // main().
    $single_item = [self::createBacktraceItem()];
    $data[] = [$single_item, self::createBacktraceItem('main()')];

    // Add a second item, without a class.
    $two_items = $single_item;
    $two_items[] = self::createBacktraceItem('test_function_two');
    $data[] = [$two_items, self::createBacktraceItem('test_function_two()')];

    // Add a second item, with a class.
    $two_items = $single_item;
    $two_items[] = self::createBacktraceItem('test_function_two', 'TestClass');
    $data[] = [$two_items, self::createBacktraceItem('TestClass->test_function_two()')];

    // Add ignored functions to backtrace. They should get removed.
    foreach (['debug', '_drupal_error_handler', '_drupal_exception_handler'] as $function) {
      $two_items = $single_item;
      // Push to the start of the backtrace.
      array_unshift($two_items, self::createBacktraceItem($function));
      $data[] = [$single_item, self::createBacktraceItem('main()')];
    }

    return $data;
  }

  /**
   * Tests the formatBacktrace() method.
   *
   * @param array $backtrace
   *   The test backtrace array.
   * @param string $expected
   *   The expected backtrace as a string.
   */
  #[DataProvider('providerTestFormatBacktrace')]
  public function testFormatBacktrace(array $backtrace, string $expected): void {
    $this->assertSame($expected, Error::formatBacktrace($backtrace));
  }

  /**
   * Data provider for testFormatBacktrace.
   *
   * @return array
   *   An array of test cases, each containing a backtrace array and its expected formatted string.
   */
  public static function providerTestFormatBacktrace(): array {
    $data = [];

    // Test with no function, main should be in the backtrace.
    $data[] = [[self::createBacktraceItem(NULL, NULL)], "main() (Line: 10)\n"];

    $base = [self::createBacktraceItem()];
    $data[] = [$base, "test_function() (Line: 10)\n"];

    // Add a second item.
    $second_item = $base;
    $second_item[] = self::createBacktraceItem('test_function_2');

    $data[] = [$second_item, "test_function() (Line: 10)\ntest_function_2() (Line: 10)\n"];

    // Add a second item, with a class.
    $second_item_class = $base;
    $second_item_class[] = self::createBacktraceItem('test_function_2', 'TestClass');

    $data[] = [$second_item_class, "test_function() (Line: 10)\nTestClass->test_function_2() (Line: 10)\n"];

    // Add a second item, with a class.
    $second_item_args = $base;
    $second_item_args[] = self::createBacktraceItem('test_function_2', NULL, ['string', 10, new \stdClass()]);

    $data[] = [$second_item_args, "test_function() (Line: 10)\ntest_function_2('string', 10, Object) (Line: 10)\n"];

    return $data;
  }

  /**
   * Creates a mock backtrace item.
   *
   * @param string|null $function
   *   (optional) The function name to use in the backtrace item.
   * @param string|null $class
   *   (optional) The class to use in the backtrace item.
   * @param array $args
   *   (optional) An array of function arguments to add to the backtrace item.
   * @param int $line
   *   (optional) The line where the function was called.
   *
   * @return array
   *   A backtrace array item.
   */
  protected static function createBacktraceItem(?string $function = 'test_function', ?string $class = NULL, array $args = [], int $line = 10): array {
    $backtrace = [
      'file' => 'test_file',
      'line' => $line,
      'function' => $function,
      'args' => [],
    ];

    if (isset($class)) {
      $backtrace['class'] = $class;
      $backtrace['type'] = '->';
    }

    if (!empty($args)) {
      $backtrace['args'] = $args;
    }

    return $backtrace;
  }

  /**
   * Defines a number of functions with guaranteed line numbers.
   *
   * Each of the functions will call the next function in the list, except the
   * last one, which will throw an exception.
   *
   * @param array<string, int> $functions_lines
   *   Line numbers by function name without namespace.
   * @param string $namespace
   *   Namespace to prepend to the function names, without leading or trailing
   *   namespace separator.
   * @param string $exception_class
   *   Exception class to throw in the last function.
   */
  protected function defineTestFunctions(array $functions_lines, string $namespace, string $exception_class): void {
    $next_function = NULL;
    assert($functions_lines !== []);
    foreach (array_reverse($functions_lines) as $function => $line) {
      $newlines = str_repeat("\n", $line - 1);
      $return_expression = ($next_function !== NULL)
        ? "$next_function()"
        : "new \\$exception_class('Something went wrong.')";
      // Use eval() to get fixed line numbers.
      // This is safe, because all values are hard-coded in this class.
      $php = <<<PHP
namespace $namespace;{$newlines}function $function(): \Exception {
  return $return_expression;
}
PHP;
      $wrapper_line = $line + 1000;
      $eval_newlines = str_repeat("\n", $wrapper_line - 1);
      // Use a second layer of eval() to get distinguishable file names.
      eval($eval_newlines . 'eval(' . var_export($php, TRUE) . ');');
      $eval_line = __LINE__ - 1;
      $reflection_function = new \ReflectionFunction("$namespace\\$function");
      $this->assertSame($line, $reflection_function->getStartLine());
      $this->assertSame(__FILE__ . "($eval_line) : eval()'d code($wrapper_line) : eval()'d code", $reflection_function->getFileName());
      $next_function = $function;
    }
  }

}
