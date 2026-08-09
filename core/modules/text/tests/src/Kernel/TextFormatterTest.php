<?php

declare(strict_types=1);

namespace Drupal\Tests\text\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\text\Plugin\Field\FieldFormatter\TextTrimmedFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore afte
/**
 * Tests the text formatters functionality.
 */
#[Group('text')]
#[RunTestsInSeparateProcesses]
class TextFormatterTest extends EntityKernelTestBase {

  /**
   * The entity type used in this test.
   *
   * @var string
   */
  protected $entityType = 'entity_test';

  /**
   * The bundle used in this test.
   *
   * @var string
   */
  protected $bundle = 'entity_test';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FilterFormat::create([
      'format' => 'my_text_format',
      'name' => 'My text format',
      'filters' => [
        'filter_autop' => [
          'module' => 'filter',
          'status' => TRUE,
        ],
      ],
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'formatted_text',
      'entity_type' => $this->entityType,
      'type' => 'text',
      'settings' => [],
    ])->save();
    FieldConfig::create([
      'entity_type' => $this->entityType,
      'bundle' => $this->bundle,
      'field_name' => 'formatted_text',
      'label' => 'Filtered text',
    ])->save();
  }

  /**
   * Tests all text field formatters.
   */
  public function testFormatters(): void {
    $formatters = [
      'text_default',
      'text_trimmed',
      'text_summary_or_trimmed',
    ];

    // Create the entity to be referenced.
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['name' => $this->randomMachineName()]);
    $entity->formatted_text = [
      'value' => 'Hello, world!',
      'format' => 'my_text_format',
    ];
    $entity->save();

    foreach ($formatters as $formatter) {
      // Verify the text field formatter's render array.
      $build = $entity->get('formatted_text')->view(['type' => $formatter]);
      \Drupal::service('renderer')->renderRoot($build[0]);
      $this->assertSame("<p>Hello, world!</p>\n", (string) $build[0]['#markup']);
      $this->assertEquals(FilterFormat::load('my_text_format')->getCacheTags(), $build[0]['#cache']['tags'], "The $formatter formatter has the expected cache tags when formatting a formatted text field.");
    }
  }

  /**
   * Tests the exclude_html_tags setting on the text_trimmed formatter.
   */
  public function testExcludeHtmlTagsSetting(): void {
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['name' => $this->randomMachineName()]);
    $entity->formatted_text = [
      'value' => '<p>abcdefghijklmnopqrst</p>',
      'format' => 'my_text_format',
    ];
    $entity->save();

    $build = $entity->get('formatted_text')->view([
      'type' => 'text_trimmed',
      'settings' => [
        'trim_length' => 10,
        'exclude_html_tags' => TRUE,
      ],
    ]);
    $output = (string) $this->container->get('renderer')->renderRoot($build);
    // With tags stripped should get the first 10 letters.
    $this->assertStringContainsString('abcdefghij', $output);
  }

  /**
   * Tests TextTrimmedFormatter::truncateHtml() across HTML shapes.
   */
  #[DataProvider('providerTruncateHtml')]
  public function testTruncateHtml(string $input, int $length, string $expected_substring): void {
    $result = TextTrimmedFormatter::truncateHtml($input, $length);
    $this->assertStringContainsString($expected_substring, $result);
  }

  /**
   * Data provider for ::testTruncateHtml().
   */
  public static function providerTruncateHtml(): array {
    return [
      'input shorter than limit is returned unchanged' => [
        '<p>Hello</p>', 100, '<p>Hello</p>',
      ],
      'tags are not counted toward the limit' => [
        '<p>abcdefghij</p>', 5, '<p>abcde</p>',
      ],
      'nested tags are repaired when the cut lands inside them' => [
        '<p><strong>Hello world</strong></p>', 5, '<strong>Hello</strong>',
      ],
      'void elements do not corrupt the counter' => [
        '<p>before<br>after</p>', 10, 'before<br>afte',
      ],
      'attribute contents do not count toward the limit' => [
        '<a href="https://example.com" title="link">click here</a>', 5, '>click</a>',
      ],
      'sibling tags share one continuous counter' => [
        '<p>one</p><p>two</p><p>three</p>', 6, '<p>two</p>',
      ],
    ];
  }

}
