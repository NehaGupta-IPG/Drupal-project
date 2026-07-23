<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Kernel;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter.
 */
#[CoversClass(MediaThumbnailFormatter::class)]
#[Group('media')]
#[RunTestsInSeparateProcesses]
class MediaThumbnailFormatterTest extends MediaKernelTestBase {

  use EntityReferenceFieldCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
  ];

  /**
   * Test media reference field name.
   *
   * @var string
   */
  protected $mediaFieldName = 'field_media';

  /**
   * Test entity type id.
   *
   * @var string
   */
  protected $testEntityTypeId = 'entity_test_with_bundle';

  /**
   * Test entity bundle id.
   *
   * @var string
   */
  protected $testEntityBundleId = 'entity_test_bundle';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->user->addRole($this->createRole(['view media']));
    $this->user->save();
    $this->installEntitySchema('entity_test_with_bundle');
    // Create an entity bundle that has a media reference field.
    $entity_test_bundle = EntityTestBundle::create([
      'id' => $this->testEntityBundleId,
    ]);
    $entity_test_bundle->save();
    $this->createEntityReferenceField(
      $this->testEntityTypeId,
      $this->testEntityBundleId,
      $this->mediaFieldName,
      $this->mediaFieldName,
      'media'
    );
  }

  /**
   * Tests the settings summary.
   *
   * @param array $settings
   *   The settings to use for the formatter.
   * @param array $expected_summary
   *   The expected settings summary.
   */
  #[DataProvider('providerTestSettingsSummary')]
  public function testSettingsSummary(array $settings, array $expected_summary): void {
    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display  */
    $display = \Drupal::service('entity_display.repository')->getViewDisplay($this->testEntityTypeId, $this->testEntityBundleId);
    $display->setComponent($this->mediaFieldName, [
      'type' => 'media_thumbnail',
      'settings' => $settings,
    ]);
    $formatter = $display->getRenderer($this->mediaFieldName);
    $actual_summary = array_map('strval', $formatter->settingsSummary());
    $this->assertSame($expected_summary, $actual_summary);
  }

  /**
   * Data provider for testSettingsSummary().
   *
   * @return array[]
   *   An array of test data.
   */
  public static function providerTestSettingsSummary(): array {
    return [
      'link to content' => [
        [
          'image_link' => 'content',
        ],
        [
          'Original image',
          'Linked to content',
          'Image loading: lazy',
        ],
      ],
      'link to media' => [
        [
          'image_link' => 'media',
        ],
        [
          'Original image',
          'Image loading: lazy',
          'Linked to media item',
        ],
      ],
      'link to nothing' => [
        [
          'image_link' => '',
        ],
        [
          'Original image',
          'Image loading: lazy',
        ],
      ],
    ];
  }

  /**
   * Tests that stale source field alt text is not rendered.
   *
   * Media::hasSourceFieldChanged() compares only the source field's main
   * property (the referenced file's target ID), so updating just the alt
   * text on an existing source field value does not trigger a thumbnail
   * update. The formatter must therefore fall back to the source field
   * itself rather than the (potentially stale) thumbnail field.
   */
  public function testViewElementsReflectsUpdatedSourceFieldAltText(): void {
    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display  */
    $display = \Drupal::service('entity_display.repository')->getViewDisplay($this->testEntityTypeId, $this->testEntityBundleId);
    $display->setComponent($this->mediaFieldName, ['type' => 'media_thumbnail']);

    $media_type = $this->createMediaType('image');
    $source_field_name = $media_type->getSource()->getConfiguration()['source_field'];

    $file = File::create(['uri' => 'public://original.jpg']);
    $file->setPermanent();
    $file->save();

    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Test media',
      $source_field_name => [
        'target_id' => $file->id(),
        'alt' => 'Original alt text',
      ],
    ]);
    $media->save();

    $host = EntityTestWithBundle::create([
      'type' => $this->testEntityBundleId,
      $this->mediaFieldName => ['target_id' => $media->id()],
    ]);
    $host->save();

    // Update only the alt text on the source field; the referenced file
    // itself is unchanged.
    $media->get($source_field_name)->alt = 'Updated alt text';
    $media->save();

    $build = $display->build($host);
    $this->assertSame('Updated alt text', $build[$this->mediaFieldName][0]['#item']->alt);
  }

}
