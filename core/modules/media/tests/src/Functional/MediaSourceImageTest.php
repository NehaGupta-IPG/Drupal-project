<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the image media source.
 */
#[Group('media')]
#[RunTestsInSeparateProcesses]
class MediaSourceImageTest extends MediaFunctionalTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test that non-main properties do not trigger source field value change.
   */
  public function testOnlyMainPropertiesTriggerSourceFieldChanged(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $media_type = $this->createMediaType('image');
    $media_type_id = $media_type->id();
    $media_type->setFieldMap(['name' => 'name']);
    $media_type->save();

    /** @var \Drupal\field\FieldConfigInterface $field */
    // Disable the alt text field, because this is not a JavaScript test and
    // the alt text field will therefore not appear without a full page refresh.
    $field = FieldConfig::load("media.$media_type_id.field_media_image");
    $settings = $field->getSettings();
    $settings['alt_field'] = TRUE;
    $settings['alt_field_required'] = FALSE;
    $field->set('settings', $settings);
    $field->save();

    $file = File::create([
      'uri' => $this->getTestFiles('image')[0]->uri,
    ]);
    $file->save();

    $media = Media::create([
      'name' => 'Custom name',
      'bundle' => $media_type_id,
      'field_media_image' => $file->id(),
    ]);
    $media->save();

    // Change only the alt of the image.
    $this->drupalGet($media->toUrl('edit-form'));
    $this->submitForm(['field_media_image[0][alt]' => 'Alt text'], 'Save');

    // Custom name should stay.
    $this->drupalGet($media->toUrl('edit-form'));
    $assert_session->fieldValueEquals('name[0][value]', 'image-test.png');

    // Remove image and attach a new one.
    $this->submitForm([], 'Remove');
    $image_media_name = 'example_1.jpeg';
    $page->attachFileToField('files[field_media_image_0]', $this->root . '/core/modules/media/tests/fixtures/' . $image_media_name);
    $page->pressButton('Save');

    $this->drupalGet($media->toUrl('edit-form'));
    $assert_session->fieldValueEquals('name[0][value]', 'example_1.jpeg');
  }

  /**
   * Tests that changing image alt text updates mapped metadata.
   */
  public function testAltChangeUpdatesMappedMetadata(): void {
    $assert_session = $this->assertSession();

    // Create image media type.
    $media_type = $this->createMediaType('image');
    $media_type_id = $media_type->id();

    // Map thumbnail alt metadata to the media name.
    $media_type->setFieldMap([
      'thumbnail_alt_value' => 'name',
    ]);
    $media_type->save();

    // Ensure alt field is enabled and optional.
    /** @var \Drupal\field\FieldConfigInterface $field */
    $field = FieldConfig::load("media.$media_type_id.field_media_image");
    $settings = $field->getSettings();
    $settings['alt_field'] = TRUE;
    $settings['alt_field_required'] = FALSE;
    $field->set('settings', $settings);
    $field->save();

    // Create image file.
    $file = File::create([
      'uri' => $this->getTestFiles('image')[0]->uri,
    ]);
    $file->save();

    // Create media with explicit name and initial alt.
    $media = Media::create([
      'bundle' => $media_type_id,
      'name' => 'Initial name',
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'Initial alt',
      ],
    ]);
    $media->save();

    // Explicit name should NOT be overridden on initial save.
    $this->drupalGet($media->toUrl('edit-form'));
    $assert_session->fieldValueEquals('name[0][value]', 'Initial name');

    // Change only the alt text.
    $this->submitForm([
      'field_media_image[0][alt]' => 'Updated alt',
    ], 'Save');

    // Name should now reflect updated alt text.
    $this->drupalGet($media->toUrl('edit-form'));
    $assert_session->fieldValueEquals('name[0][value]', 'Updated alt');
  }

}
