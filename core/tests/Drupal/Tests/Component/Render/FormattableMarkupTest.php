<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Render;

// cspell:ignore errfile errline

use Drupal\Component\Render\FormattableMarkup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the FormattableMarkup class.
 */
#[CoversClass(FormattableMarkup::class)]
#[Group('utility')]
class FormattableMarkupTest extends TestCase {

  /**
   * @legacy-covers ::__toString
   * @legacy-covers ::jsonSerialize
   */
  public function testToString(): void {
    $string = 'Can I have a @replacement';
    $formattable_string = new FormattableMarkup($string, ['@replacement' => 'kitten']);
    $text = (string) $formattable_string;
    $this->assertEquals('Can I have a kitten', $text);
    $text = $formattable_string->jsonSerialize();
    $this->assertEquals('Can I have a kitten', $text);
  }

  /**
   * Tests count.
   */
  public function testCount(): void {
    $string = 'Can I have a @replacement';
    $formattable_string = new FormattableMarkup($string, ['@replacement' => 'kitten']);
    $this->assertEquals(strlen($string), $formattable_string->count());
  }

  /**
   * @legacy-covers ::__toString
   */
  #[DataProvider('providerTestUnexpectedPlaceholder')]
  public function testUnexpectedPlaceholder(string $string, array $arguments, ?int $error_number, string $error_message): void {
    // We set a custom error handler that saves the last error.
    // We need this custom error handler because we cannot rely on the error to
    // exception conversion as __toString is never allowed to leak any kind of
    // exception.
    // https://github.com/sebastianbergmann/phpunit/issues/487
    $last_error_number = NULL;
    $last_error_message = '';
    set_error_handler(function (int $errno, string $errstr) use (&$last_error_number, &$last_error_message): bool {
      $last_error_number = $errno;
      $last_error_message = $errstr;
      return TRUE;
    });
    // We want this to trigger an error.
    $markup = new FormattableMarkup($string, $arguments);
    // Cast it to a string which will generate the errors.
    $output = (string) $markup;
    restore_error_handler();
    // The string should not change.
    $this->assertEquals($string, $output);
    $this->assertEquals($error_number, $last_error_number);
    $this->assertEquals($error_message, $last_error_message);
  }

  /**
   * Data provider for FormattableMarkupTest::testUnexpectedPlaceholder().
   *
   * @return array
   *   An array of test cases.
   */
  public static function providerTestUnexpectedPlaceholder(): array {
    return [
      [
        'Non alpha, non-allowed starting character: ~placeholder',
        ['~placeholder' => 'replaced'],
        E_USER_WARNING,
        'Placeholders must begin with one of the following "@", ":" or "%", invalid placeholder (~placeholder) with string: "Non alpha, non-allowed starting character: ~placeholder"',
      ],
      [
        'Alpha starting character: placeholder',
        ['placeholder' => 'replaced'],
        NULL,
        '',
      ],
      // Ensure that where the placeholder is located in the string is
      // irrelevant.
      ['placeholder', ['placeholder' => 'replaced'], NULL, ''],
      ['No replacements', ['foo' => 'bar'], NULL, ''],
    ];
  }

  /**
   * Tests NULL values in @ and % placeholders throw AssertionError in dev.
   *
   * @param string $string
   *   A string to be formatted.
   * @param array $arguments
   *   The arguments to replace.
   * @param string $expected_key
   *   The placeholder key expected in the AssertionError message.
   * @param string $expected
   *   Unused in this variant; kept for shared provider compatibility.
   *
   * @legacy-covers ::__toString
   */
  #[DataProvider('providerTestNullPlaceholderAssertion')]
  public function testNullPlaceholderAssertionError(string $string, array $arguments, string $expected_key, string $expected): void {
    ini_set('zend.assertions', 1);
    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessageMatches('/' . preg_quote($expected_key, '/') . '/');
    $markup = new FormattableMarkup($string, $arguments);
    (string) $markup;
  }

  /**
   * Tests NULL @ and % placeholders fall back to empty string in production.
   *
   * @param string $string
   *   A string to be formatted.
   * @param array $arguments
   *   The arguments to replace.
   * @param string $expected_key
   *   Unused in this variant; kept for shared provider compatibility.
   * @param string $expected
   *   Expected output.
   *
   * @legacy-covers ::__toString
   */
  #[DataProvider('providerTestNullPlaceholderAssertion')]
  public function testNullPlaceholderProductionFallback(string $string, array $arguments, string $expected_key, string $expected): void {
    ini_set('zend.assertions', 0);
    $markup = new FormattableMarkup($string, $arguments);
    $output = (string) $markup;
    ini_set('zend.assertions', 1);
    $this->assertEquals($expected, $output);
  }

  /**
   * Data provider for NULL placeholder assertion/fallback tests.
   */
  public static function providerTestNullPlaceholderAssertion(): array {
    return [
      'at-placeholder' => [
        'String with @placeholder',
        ['@placeholder' => NULL],
        '@placeholder',
        'String with ',
      ],
      'percent-placeholder' => [
        'String with %placeholder',
        ['%placeholder' => NULL],
        '%placeholder',
        'String with <em class="placeholder"></em>',
      ],
      'multiple-at' => [
        '@first and @second',
        ['@first' => NULL, '@second' => 'value'],
        '@first',
        ' and value',
      ],
      'mixed-types' => [
        'User @name with id %id',
        ['@name' => NULL, '%id' => '123'],
        '@name',
        'User  with id <em class="placeholder">123</em>',
      ],
    ];
  }

  /**
   * Tests multiple NULL placeholders throw on the first in dev mode.
   *
   * @legacy-covers ::__toString
   */
  public function testMultipleNullPlaceholders(): void {
    ini_set('zend.assertions', 1);
    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessageMatches('/@first/');
    // @phpstan-ignore argument.type (Tests intentional NULL placeholder values.)
    (string) new FormattableMarkup('@first and @second', ['@first' => NULL, '@second' => NULL]);
  }

  /**
   * Tests multiple NULL placeholders both fall back to empty string in production.
   *
   * @legacy-covers ::__toString
   */
  public function testMultipleNullPlaceholdersProductionFallback(): void {
    ini_set('zend.assertions', 0);
    // @phpstan-ignore argument.type (Tests intentional NULL placeholder values.)
    $output = (string) new FormattableMarkup('@first and @second', ['@first' => NULL, '@second' => NULL]);
    ini_set('zend.assertions', 1);
    $this->assertEquals(' and ', $output);
  }

  /**
   * Tests NULL values in : placeholders are silently converted to empty string.
   *
   * @param string $string
   *   A string to be formatted.
   * @param array $arguments
   *   The arguments to replace.
   * @param string $expected
   *   Expected result.
   *
   * @legacy-covers ::__toString
   */
  #[DataProvider('providerTestNullColonPlaceholderSilent')]
  public function testNullColonPlaceholderSilent(string $string, array $arguments, string $expected): void {
    $markup = new FormattableMarkup($string, $arguments);
    $output = (string) $markup;
    $this->assertEquals($expected, $output);
  }

  /**
   * Data provider for testNullColonPlaceholderSilent().
   */
  public static function providerTestNullColonPlaceholderSilent(): array {
    return [
      'colon-url' => ['<a href=":url">Link</a>', [':url' => NULL], '<a href="">Link</a>'],
      'colon-src' => ['<img src=":src" alt="Image">', [':src' => NULL], '<img src="" alt="Image">'],
      'colon-multiple' => [
        '<a href=":url" data-id=":id">Link</a>',
        [':url' => NULL, ':id' => NULL],
        '<a href="" data-id="">Link</a>',
      ],
    ];
  }

}
