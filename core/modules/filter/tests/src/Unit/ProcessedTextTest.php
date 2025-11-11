<?php

declare(strict_types=1);

namespace Drupal\Tests\filter\Unit;

use Drupal\filter\Element\ProcessedText;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\filter\Element\ProcessedText.
 */
#[CoversClass(ProcessedText::class)]
#[Group('filter')]
final class ProcessedTextTest extends UnitTestCase {

  /**
   * The processed text render element.
   *
   * @var \Drupal\filter\Element\ProcessedText
   */
  protected ProcessedText $element;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $configuration = [];
    $this->element = new ProcessedText($configuration, 'processed_text', ['provider' => 'test']);
    parent::setUp();
  }

  /**
   * Tests preRenderText.
   *
   * @param array $element
   *   Input render element.
   * @param array $expected
   *   The expected output render element.
   *
   * @legacy-covers ::preRenderText
   */
  #[DataProvider('providerElement')]
  public function testPreRenderText(array $element, array $expected): void {
    $this->assertSame($expected, $this->element::preRenderText($element, 'en')->getInfo());
  }

  /**
   * Provides data for testPreRenderText.
   *
   * @return array
   *   An array of test data.
   */
  public static function providerElement(): array {
    return [];
  }

}
