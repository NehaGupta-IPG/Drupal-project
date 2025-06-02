<?php

declare(strict_types=1);

namespace Drupal\Tests\ckeditor5\FunctionalJavascript;

use Drupal\ckeditor5\Plugin\Editor\CKEditor5;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\NodeInterface;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * @coversDefaultClass \Drupal\ckeditor5\Plugin\CKEditor5Plugin\Image
 * @group ckeditor5
 * @group #slow
 * @internal
 */
class ImageUrlTest extends ImageUrlTestBase {
  use ImageTestBaselineTrait;

  /**
   * A host entity with a body field that has image in it.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $hostWithImage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FilterFormat::create([
      'format' => 'test_format_no_filter',
      'name' => 'Test format without HTML filter',
      'filters' => [],
    ])->save();

    Editor::create([
      'editor' => 'ckeditor5',
      'format' => 'test_format_no_filter',
      'settings' => [
        'toolbar' => [
          'items' => [
            'drupalInsertImage',
          ],
        ],
        'plugins' => [
          'ckeditor5_imageResize' => [
            'allow_resize' => TRUE,
          ],
        ],
      ],
      'image_upload' => [
        'status' => FALSE,
      ],
    ])->save();
    $this->assertSame([], array_map(
      function (ConstraintViolation $v) {
        return (string) $v->getMessage();
      },
      iterator_to_array(CKEditor5::validatePair(
        Editor::load('test_format_no_filter'),
        FilterFormat::load('test_format_no_filter')
      ))
    ));

    $src = $this->imageAttributes()['src'];
    $this->hostWithImage = $this->createNode([
      'type' => 'page',
      'title' => 'Animals with strange images',
      'body' => [
        'value' => "<img src=\"{$src}\">",
        'format' => 'test_format_no_filter',
      ],
    ]);
    $this->hostWithImage->save();
    $this->adminUser = $this->drupalCreateUser([
      'use text format test_format',
      'use text format test_format_no_filter',
      'bypass node access',
      'administer filters',
    ]);

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests the Drupal image URL widget.
   */
  public function testImageUrlWidget(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $image_selector = '.ck-widget.image-inline';
    $src = $this->imageAttributes()['src'];

    $this->drupalGet($this->host->toUrl('edit-form'));
    $this->waitForEditor();

    $this->pressEditorButton('Insert image via URL');
    $dialog = $page->find('css', '.ck-dialog');
    $src_input = $dialog->find('css', '.ck-image-insert-url input[type=text]');
    $src_input->setValue($src);
    $dialog->find('xpath', "//button[span[text()='Accept']]")->click();

    $this->assertNotEmpty($assert_session->waitForElementVisible('css', $image_selector));
    $this->click($image_selector);
    $this->assertVisibleBalloon('[aria-label="Image toolbar"]');

    $this->pressEditorButton('Update image URL');
    $dialog = $page->find('css', '.ck-dialog');
    $src_input = $dialog->find('css', '.ck-image-insert-url input[type=text]');
    $this->assertEquals($src, $src_input->getValue());
  }

  /**
   * Tests the Drupal image URL widget properly updates existing image.
   *
   * Tests with htmlSupport.GeneralHtmlSupport plugin enabled. It is enabled
   * automatically when filter_html is disabled.
   */
  public function testImageUrlUpdateWidget(): void {
    $this->drupalGet($this->hostWithImage->toUrl('edit-form'));
    $this->waitForEditor();

    $image_selector = '.ck-widget.image';
    $this->click($image_selector);
    $page = $this->getSession()->getPage();
    $this->pressEditorButton('Update image URL');
    $panel = $page->find('css', '[aria-label="Update image URL"]');
    $src_input = $panel->find('css', '.ck-input-text');
    $updated_image_src = base_path() . 'core/misc/help.png';
    $src_input->setValue($updated_image_src);
    $panel->find('css', '.ck-button-action')->click();
    $this->assertSession()->waitForElementVisible('css', "img[src=\"$updated_image_src\"]");

    $this->assertStringContainsString($updated_image_src, $this->getEditorDataAsHtmlString(), 'Image was not updated');
  }

}
