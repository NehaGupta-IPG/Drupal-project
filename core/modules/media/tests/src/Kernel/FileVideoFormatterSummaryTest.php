<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Kernel;

use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the file video formatter summary.
 */
#[Group('media')]
#[RunTestsInSeparateProcesses]
class FileVideoFormatterSummaryTest extends MediaKernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testMediaType = $this->createMediaType('video_file');
  }

  /**
   * Tests that the settings summary is displayable for values.
   *
   * @param mixed[] $settings
   *   The formatter settings to use for the media source field.
   * @param string[] $expectedSummary
   *   The expected summary.
   */
  #[DataProvider('provideVideoFileFormatterSettings')]
  public function testSettingsSummary(array $settings, array $expectedSummary): void {
    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay('media', $this->testMediaType->id(), 'default');
    $definition = $this->testMediaType->getSource()->getSourceFieldDefinition($this->testMediaType);
    $display->setComponent($definition->getName(), [
      'type' => 'file_video',
      'settings' => $settings,
    ]);
    /** @var \Drupal\file\Plugin\Field\FieldFormatter\FileVideoFormatter $formatter */
    $formatter = $display->getRenderer($definition->getName());
    $actual_summary = array_map('strval', $formatter->settingsSummary());
    $this->assertSame($expectedSummary, $actual_summary);
  }

  /**
   * Data provider for testSettingsSummary().
   *
   * @return array[]
   *   An array of test data.
   */
  public static function provideVideoFileFormatterSettings(): array {
    return [
      'width and height are both set' => [
        [
          'width' => 640,
          'height' => 480,
        ],
        [
          'Playback controls: <em class="placeholder">visible</em>',
          'Autoplay: <em class="placeholder">no</em>',
          'Loop: <em class="placeholder">no</em>',
          'Multiple file display: Multiple HTML tags',
          'Muted: <em class="placeholder">no</em>',
          'Plays Inline: <em class="placeholder">no</em>',
          'Size: <em class="placeholder">640</em> x <em class="placeholder">480</em> pixels',
          'Width: <em class="placeholder">640</em> pixels',
          'Height: <em class="placeholder">480</em> pixels',
        ],
      ],
      'width is null' => [
        [
          'width' => NULL,
          'height' => 480,
        ],
        [
          'Playback controls: <em class="placeholder">visible</em>',
          'Autoplay: <em class="placeholder">no</em>',
          'Loop: <em class="placeholder">no</em>',
          'Multiple file display: Multiple HTML tags',
          'Muted: <em class="placeholder">no</em>',
          'Plays Inline: <em class="placeholder">no</em>',
          'Size: <em class="placeholder">auto</em> x <em class="placeholder">480</em> pixels',
          'Height: <em class="placeholder">480</em> pixels',
        ],
      ],
      'height is null' => [
        [
          'width' => 640,
          'height' => NULL,
        ],
        [
          'Playback controls: <em class="placeholder">visible</em>',
          'Autoplay: <em class="placeholder">no</em>',
          'Loop: <em class="placeholder">no</em>',
          'Multiple file display: Multiple HTML tags',
          'Muted: <em class="placeholder">no</em>',
          'Plays Inline: <em class="placeholder">no</em>',
          'Size: <em class="placeholder">640</em> x <em class="placeholder">auto</em> pixels',
          'Width: <em class="placeholder">640</em> pixels',
        ],
      ],
      'width and height are null' => [
        [
          'width' => NULL,
          'height' => NULL,
        ],
        [
          'Playback controls: <em class="placeholder">visible</em>',
          'Autoplay: <em class="placeholder">no</em>',
          'Loop: <em class="placeholder">no</em>',
          'Multiple file display: Multiple HTML tags',
          'Muted: <em class="placeholder">no</em>',
          'Plays Inline: <em class="placeholder">no</em>',
          'Size: <em class="placeholder">auto</em> x <em class="placeholder">auto</em> pixels',
        ],
      ],
    ];
  }

}
