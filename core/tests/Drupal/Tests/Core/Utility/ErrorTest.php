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
   * @return \Iterator<string, array{list<array>, array}>
   *   An iterator with named datasets.
   *   (Using an iterator instead of an array helps to avoid duplicate dataset
   *   names.)
   */
  public static function providerTestGetLastCaller(): \Iterator {
    // Test with just one item. This should default to the function being
    // main().
    yield 'single item' => [
      [
        self::createBacktraceItem('foo', line: 51, file: 'main.php'),
      ],
      self::createBacktraceItem('main()', line: 51, file: 'main.php'),
    ];

    // Test with more procedural items.
    // The function name from the first item is always ignored.
    yield 'bar, foo, other' => [
      [
        self::createBacktraceItem('bar', line: 55, file: 'foo.php'),
        self::createBacktraceItem('foo', line: 66, file: 'other.php'),
        self::createBacktraceItem('other', line: 77, file: 'main.php'),
      ],
      self::createBacktraceItem('foo()', line: 55, file: 'foo.php'),
    ];

    // Test with a class method as second item.
    yield 'TestClass' => [
      [
        self::createBacktraceItem('foo', file: 'TestClass.php'),
        self::createBacktraceItem('testMethod', 'TestClass', file: 'other.php'),
        self::createBacktraceItem('other', file: 'main.php'),
      ],
      self::createBacktraceItem('TestClass->testMethod()', file: 'TestClass.php'),
    ];

    // Test with NULL as the first item's function name.
    // This replicates the behavior from Error::decodeException(), which
    // prepends an artificial item without function name.
    yield 'NULL, bar, foo' => [
      [
        self::createBacktraceItem(NULL, line: 77, file: 'bar.php'),
        self::createBacktraceItem('bar', file: 'foo.php'),
        self::createBacktraceItem('foo', file: 'index.php'),
      ],
      self::createBacktraceItem('bar()', line: 77, file: 'bar.php'),
    ];

    // Prepend ignored functions to backtrace. They should get removed.
    foreach (['debug', '_drupal_error_handler', '_drupal_exception_handler'] as $function) {
      yield "NULL, $function, bar, foo" => [
        [
          self::createBacktraceItem(NULL),
          self::createBacktraceItem($function, file: 'bar.php'),
          self::createBacktraceItem('bar', file: 'foo.php'),
          self::createBacktraceItem('foo', file: 'index.php'),
        ],
        self::createBacktraceItem('bar()', file: 'bar.php'),
      ];
    }

    // Test removal of multiple ignored functions.
    yield 'NULL, debug, debug' => [
      [
        self::createBacktraceItem(NULL),
        self::createBacktraceItem('debug'),
        self::createBacktraceItem('_drupal_exception_handler'),
        self::createBacktraceItem('foo'),
      ],
      self::createBacktraceItem('foo()'),
    ];
  }

  /**
   * Tests the getLastCaller() method with a real stack trace.
   */
  public function testGetLastCallerReal(): void {
    $namespace = self::class;
    $exception_class = \RuntimeException::class;
    $this->defineTestFunctions([
      'foo900' => 900,
      'foo300' => 300,
      'foo600' => 600,
      'foo500' => 500,
    ], $namespace, $exception_class);

    $exception = "$namespace\\foo300"();
    $this->assertInstanceOf($exception_class, $exception);
    $this->assertInstanceOf(\Throwable::class, $exception);
    $this->assertSame(501, $exception->getLine());

    $file_template = __FILE__ . "(%s) : eval()'d code(%s) : eval()'d code";
    $this->assertGreaterThan(0, preg_match(
      '@^' . sprintf(preg_quote($file_template, '@'), '(\d+)', '(\d+)') . '$@',
      $exception->getFile(),
      $matches,
    ));
    $this->assertSame('1500', $matches[2]);
    $get_file = fn (int $line) => sprintf($file_template, $matches[1], $line);
    $this->assertSame($get_file(1500), $exception->getFile());

    // The exception is thrown in the first line of foo500().
    $this->assertSame(501, $exception->getLine());
    // The stack trace is as expected.
    $this->assertSame("$namespace\\foo500", $exception->getTrace()[0]['function']);
    $this->assertSame($get_file(1600), $exception->getTrace()[0]['file']);
    $this->assertSame("$namespace\\foo600", $exception->getTrace()[1]['function']);
    $this->assertSame("$namespace\\foo300", $exception->getTrace()[2]['function']);

    // The top item is skipped with ::getLastCaller().
    $backtrace = $exception->getTrace();
    $this->assertEqualsCanonicalizing([
      'file' => $get_file(1600),
      'line' => 601,
      'function' => 'Drupal\Tests\Core\Utility\ErrorTest\foo600()',
    ], Error::getLastCaller($backtrace));
    $this->assertSame($exception->getTrace(), $backtrace);

    // Prepend the additional item as in Error::decodeException().
    $get_enhanced_backtrace = fn () => [
      ['line' => $exception->getLine(), 'file' => $exception->getFile()],
      ...$exception->getTrace(),
    ];
    $enhanced_backtrace = $get_enhanced_backtrace();
    // The extra item is skipped, and we get the first "real" item.
    $this->assertEqualsCanonicalizing([
      'file' => $exception->getFile(),
      'line' => 501,
      'function' => 'Drupal\Tests\Core\Utility\ErrorTest\foo500()',
    ], Error::getLastCaller($enhanced_backtrace));
    $this->assertSame($get_enhanced_backtrace(), $enhanced_backtrace);

    // Simulate ignored functions.
    $ignored_functions_property = new \ReflectionProperty(Error::class, 'ignoredFunctions');
    $original_ignored_functions = $ignored_functions_property->getValue();
    try {
      $ignored_functions_property->setValue(NULL, [
        ...$original_ignored_functions,
        "$namespace\\foo500",
        "$namespace\\foo600",
      ]);

      $backtrace = $exception->getTrace();
      $this->assertEqualsCanonicalizing([
        'file' => $get_file(1300),
        'line' => 301,
        'function' => 'Drupal\Tests\Core\Utility\ErrorTest\foo300()',
      ], Error::getLastCaller($backtrace));
      $this->assertSame(array_slice($exception->getTrace(), 1), $backtrace);

      $enhanced_backtrace = $get_enhanced_backtrace();
      $this->assertEqualsCanonicalizing([
        'file' => $get_file(1300),
        'line' => 301,
        'function' => 'Drupal\Tests\Core\Utility\ErrorTest\foo300()',
      ], Error::getLastCaller($enhanced_backtrace));
      $this->assertSame(array_slice($exception->getTrace(), 1), $enhanced_backtrace);
    }
    finally {
      $ignored_functions_property->setValue(NULL, $original_ignored_functions);
    }
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
   * @param string|null $file
   *   (optional) The file where the function was called.
   *
   * @return array
   *   A backtrace array item.
   */
  protected static function createBacktraceItem(?string $function = 'test_function', ?string $class = NULL, array $args = [], int $line = 10, ?string $file = 'test_file'): array {
    $backtrace = [
      'file' => $file,
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
      // phpcs:ignore Drupal.Functions.DiscouragedFunctions.Discouraged
      eval($eval_newlines . 'eval(' . var_export($php, TRUE) . ');');
      $eval_line = __LINE__ - 1;
      $reflection_function = new \ReflectionFunction("$namespace\\$function");
      $this->assertSame($line, $reflection_function->getStartLine());
      $this->assertSame(__FILE__ . "($eval_line) : eval()'d code($wrapper_line) : eval()'d code", $reflection_function->getFileName());
      $next_function = $function;
    }
  }

}
