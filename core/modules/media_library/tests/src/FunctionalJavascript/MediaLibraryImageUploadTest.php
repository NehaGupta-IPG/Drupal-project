<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests the handling of images uploaded to the media library.
 */
#[Group('media_library')]
#[RunTestsInSeparateProcesses]
class MediaLibraryImageUploadTest extends MediaLibraryTestBase {

  use EntityReferenceFieldCreationTrait;
  use MediaTypeCreationTrait;

  /**
   * Tests that oversized images are automatically resized on upload.
   */
  public function testImageResizing(): void {
    // Create a media type that only accepts images up to 16x16 in size.
    $media_type = $this->createMediaType('image');
    $media_type->getSource()
      ->getSourceFieldDefinition($media_type)
      ->setSetting('max_resolution', '16x16')
      ->save();

    $node_type = $this->drupalCreateContentType()->id();
    $this->createEntityReferenceField('node', $node_type, 'field_icon', 'Icon', 'media');
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', $node_type)
      ->setComponent('field_icon', [
        'type' => 'media_library_widget',
      ])
      ->save();

    $account = $this->drupalCreateUser([
      "create $node_type content",
      'create ' . $media_type->id() . ' media',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet("/node/add/$node_type");
    $this->openMediaLibraryForField('field_icon');

    $image_uri = uniqid('public://') . '.png';
    $image_uri = $this->getRandomGenerator()->image($image_uri, '16x16', '32x32');
    $image_path = $this->container->get('file_system')->realpath($image_uri);
    $this->assertNotEmpty($image_path);
    $this->assertFileExists($image_path);

    // Wait for the media library/dialog to appear.
    $this->assertNotEmpty($this->assertSession()->waitForElementVisible('css', '.media-library, .ui-dialog'));

    // Try a set of selectors that the input might be rendered with in different envs.
    $selectors = [
      '.media-library input[type="file"]',
      '.ui-dialog input[type="file"]',
      '.dropzone input[type="file"]',
      'input[type="file"].js-media-library-dropzone',
      'input[type="file"]',
    ];

    $file_input = NULL;
    foreach ($selectors as $sel) {
      $element = $this->getSession()->getPage()->find('css', $sel);
      if ($element) {
        // Ensure the input is visible so attachFile will work in some drivers.
        $this->getSession()->executeScript("var el = document.querySelector(" . json_encode($sel) . "); if (el) { el.style.display = 'block'; el.style.visibility = 'visible'; el.style.opacity = 1; }");
        $file_input = $element;
        break;
      }
    }

    if (!$file_input) {
      // Dump HTML to help debug why the input isn't present in CI.
      $this->fail('File upload input not found in media library modal. Page HTML: ' . substr($this->getSession()->getPage()->getHtml(), 0, 2000));
    }

    $file_input->attachFile($image_path);
    $this->waitForText('The image was resized to fit within the maximum allowed dimensions of 16x16 pixels.');
  }

}
