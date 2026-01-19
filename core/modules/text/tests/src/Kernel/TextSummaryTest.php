<?php

declare(strict_types=1);

namespace Drupal\Tests\text\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\filter\Render\FilteredMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests text_summary() with different strings and lengths.
 */
#[Group('text')]
#[RunTestsInSeparateProcesses]
class TextSummaryTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
    'text',
    'field',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['text']);
  }

  /**
   * Tests text summaries for a question followed by a sentence.
   */
  public function testFirstSentenceQuestion(): void {
    $text = 'A question? A sentence. Another sentence.';
    $expected = 'A question? A sentence.';
    $this->assertTextSummary($text, $expected, NULL, 30);
  }

  /**
   * Tests summary with long example.
   */
  public function testLongSentence(): void {
    // 125.
    // cSpell:disable
    $text =
      'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. ' .
      // 108.
      'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. ' .
      // 103.
      'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. ' .
      // 110.
      'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.';
    $expected = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. ' .
                'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. ' .
                'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.';
    // cSpell:enable
    // First three sentences add up to: 336, so add one for space and then 3 to
    // get half-way into next word.
    $this->assertTextSummary($text, $expected, NULL, 340);
  }

  /**
   * Tests various summary length edge cases.
   */
  public function testLength(): void {
    FilterFormat::create([
      'format' => 'autop',
      'name' => 'Autop',
      'filters' => [
        'filter_autop' => [
          'status' => 1,
        ],
      ],
    ])->save();
    FilterFormat::create([
      'format' => 'autop_correct',
      'name' => 'Autop correct',
      'filters' => [
        'filter_autop' => [
          'status' => 1,
        ],
        'filter_htmlcorrector' => [
          'status' => 1,
        ],
      ],
    ])->save();

    // This string tests a number of edge cases.
    $text = "<p>\nHi\n</p>\n<p>\nfolks\n<br />\n!\n</p>";

    // The summaries we expect text_summary() to return when $size is the index
    // of each array item.
    // Using no text format:
    $format = NULL;
    $i = 0;
    $this->assertTextSummary($text, "<p>\nHi\n</p>\n<p>\nfolks\n<br />\n!\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<", $format, $i++);
    $this->assertTextSummary($text, "<p", $format, $i++);
    $this->assertTextSummary($text, "<p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\n", $format, $i++);
    $this->assertTextSummary($text, "<p>\nH", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n<", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>\n<p>\nfolks\n<br />\n!\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>\n<p>\nfolks\n<br />\n!\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>\n<p>\nfolks\n<br />\n!\n</p>", $format, $i++);

    // Using a text format with filter_autop enabled.
    $format = 'autop';
    $i = 0;
    $this->assertTextSummary($text, "<p>\nHi\n</p>\n<p>\nfolks\n<br />\n!\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<", $format, $i++);
    $this->assertTextSummary($text, "<p", $format, $i++);
    $this->assertTextSummary($text, "<p>", $format, $i++);
    $this->assertTextSummary($text, "<p>", $format, $i++);
    $this->assertTextSummary($text, "<p>", $format, $i++);
    $this->assertTextSummary($text, "<p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>\n<p>\nfolks\n<br />\n!\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>\n<p>\nfolks\n<br />\n!\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>\n<p>\nfolks\n<br />\n!\n</p>", $format, $i++);

    // Using a text format with filter_autop and filter_htmlcorrector enabled.
    $format = 'autop_correct';
    $i = 0;
    $this->assertTextSummary($text, "<p>\nHi\n</p>\n<p>\nfolks\n<br />\n!\n</p>", $format, $i++);
    $this->assertTextSummary($text, "", $format, $i++);
    $this->assertTextSummary($text, "<p></p>", $format, $i++);
    $this->assertTextSummary($text, "<p></p>", $format, $i++);
    $this->assertTextSummary($text, "<p></p>", $format, $i++);
    $this->assertTextSummary($text, "<p></p>", $format, $i++);
    $this->assertTextSummary($text, "<p></p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>\n<p>\nfolks\n<br />\n!\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>\n<p>\nfolks\n<br />\n!\n</p>", $format, $i++);
    $this->assertTextSummary($text, "<p>\nHi\n</p>\n<p>\nfolks\n<br />\n!\n</p>", $format, $i++);
  }

  /**
   * Tests text summaries with an invalid filter format.
   *
   * @see text_summary()
   */
  public function testInvalidFilterFormat(): void {

    $this->assertTextSummary($this->randomString(100), '', 'non_existent_format');
  }

  /**
   * Calls text_summary() and asserts that the expected teaser is returned.
   *
   * @internal
   */
  public function assertTextSummary(string $text, string $expected, ?string $format = NULL, ?int $size = NULL): void {
    $summary = text_summary($text, $format, $size);
    $this->assertSame($expected, $summary, '<pre style="white-space: pre-wrap">' . $summary . '</pre> is identical to <pre style="white-space: pre-wrap">' . $expected . '</pre>');
  }

  /**
   * Test text normalization when filter_html or filter_htmlcorrector enabled.
   */
  public function testNormalization(): void {
    FilterFormat::create([
      'format' => 'filter_html_enabled',
      'name' => 'Filter HTML enabled',
      'filters' => [
        'filter_html' => [
          'status' => 1,
          'settings' => [
            'allowed_html' => '<strong>',
          ],
        ],
      ],
    ])->save();
    FilterFormat::create([
      'format' => 'filter_htmlcorrector_enabled',
      'name' => 'Filter HTML corrector enabled',
      'filters' => [
        'filter_htmlcorrector' => [
          'status' => 1,
        ],
      ],
    ])->save();
    FilterFormat::create([
      'format' => 'neither_filter_enabled',
      'name' => 'Neither filter enabled',
      'filters' => [],
    ])->save();

    $filtered_markup = FilteredMarkup::create('<div><strong><span>Hello World</span></strong></div>');
    // With either HTML filter enabled, text_summary() will normalize the text
    // using HTML::normalize().
    $summary = text_summary($filtered_markup, 'filter_html_enabled', 30);
    $this->assertStringContainsString('<div><strong><span>', $summary);
    $this->assertStringContainsString('</span></strong></div>', $summary);
    $summary = text_summary($filtered_markup, 'filter_htmlcorrector_enabled', 30);
    $this->assertStringContainsString('<div><strong><span>', $summary);
    $this->assertStringContainsString('</span></strong></div>', $summary);
    // If neither filter is enabled, the text will not be normalized.
    $summary = text_summary($filtered_markup, 'neither_filter_enabled', 30);
    $this->assertStringContainsString('<div><strong><span>', $summary);
    $this->assertStringNotContainsString('</span></strong></div>', $summary);
  }

}
