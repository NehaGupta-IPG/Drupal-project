<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Validation;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * Test class for Html constraint.
 *
 * @group Validation
 *
 * @covers \Drupal\Core\Validation\Plugin\Validation\Constraint\HtmlConstraint
 * @covers \Drupal\Core\Validation\Plugin\Validation\Constraint\HtmlConstraintValidator
 */
class HtmlConstraintTest extends KernelTestBase {

  /**
   * Tests html constraint.
   *
   * @param string $html
   *   The html string to test.
   * @param array $errors
   *   An array of errors to expect.
   * @param string $mode
   *   Set mode to document or fragment.
   *
   * @dataProvider htmlConstraintDataProvider
   */
  public function testHtmlConstraint(string $html, array $errors, string $mode = 'fragment'): void {
    $definition = DataDefinition::create('string');

    $definition->addConstraint('Html', ['mode' => $mode]);
    $testString = $this->container->get('typed_data_manager')->create($definition);
    $testString->setValue($html);
    $violations = $testString->validate();
    $this->assertCount(count($errors), $violations);
    foreach ($violations as $violation) {
      $this->assertTrue(in_array((string) $violation->getMessage(), $errors, TRUE));
    }

  }

  /**
   * Tests that an InvalidArgumentException is thrown on a bad parsing mode.
   */
  public function testHtmlConstraintArguments(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid HTML parsing mode. the `mode` argument must be "fragment" or "document".');
    $definition = DataDefinition::create('string');
    $definition->addConstraint('Html', ['mode' => 'unsupported']);
    $testString = $this->container->get('typed_data_manager')->create($definition);
    $testString->setValue("<p>Test</p>");
    $testString->validate();
  }

  /**
   * Provides data for testValidRegexConstraint().
   *
   * @return array[]
   *   The test cases.
   */
  public static function htmlConstraintDataProvider(): array {
    return [
      'valid_document' => [
        'html' => "<!doctype html>\n<html>\n<head></head><body><p>test</p></body></html>",
        'errors' => [],
        'mode' => 'document',
      ],
      'invalid_document' => [
        'html' => "<!doctype html>\n<html>\n<head></head><body><p>test</a></body></html>",
        'errors' => ['Line 0, Col 0: Could not find closing tag for a'],
        'mode' => 'document',
      ],
      'valid_document_parsed_as_fragment' => [
        'html' => "<!doctype html>\n<html>\n<head></head><body><p>test</p></body></html>",
        'errors' => ['Line 0, Col 0: Illegal placement of DOCTYPE tag. Ignoring: html'],
        'mode' => 'fragment',
      ],
      'valid_fragment' => [
        'html' => '<p>test</p>',
        'errors' => [],
        'mode' => 'fragment',
      ],
      'invalid_fragment' => [
        'html' => '<p>test</a>',
        'errors' => ['Line 0, Col 0: Could not find closing tag for a'],
        'mode' => 'fragment',
      ],
      'valid_fragment_parsed_as_document'  => [
        'html' => '<p>test</p>',
        'errors' => ['Line 0, Col 0: No DOCTYPE specified.'],
        'mode' => 'document',
      ],
    ];
  }

}
