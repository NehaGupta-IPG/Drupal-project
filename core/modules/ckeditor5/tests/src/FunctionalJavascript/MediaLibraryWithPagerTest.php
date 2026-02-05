<?php

declare(strict_types=1);

namespace Drupal\Tests\ckeditor5\FunctionalJavascript;

use Drupal\ckeditor5\Plugin\CKEditor5Plugin\MediaLibrary;
use Drupal\editor\Entity\Editor;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media\Entity\Media;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\ckeditor5\Plugin\CKEditor5Plugin\MediaLibrary.
 *
 * @internal
 */
#[CoversClass(MediaLibrary::class)]
#[Group('ckeditor5')]
#[RunTestsInSeparateProcesses]
class MediaLibraryWithPagerTest extends WebDriverTestBase {

  use MediaTypeCreationTrait;
  use TestFileCreationTrait;
  use CKEditor5TestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ckeditor5',
    'media_library',
    'node',
    'media',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'Test format',
      'filters' => [
        'media_embed' => ['status' => TRUE],
      ],
    ])->save();
    Editor::create([
      'editor' => 'ckeditor5',
      'format' => 'test_format',
      'image_upload' => [
        'status' => FALSE,
      ],
      'settings' => [
        'toolbar' => [
          'items' => [
            'drupalMedia',
          ],
        ],
        'plugins' => [
          'media_media' => [
            'allow_view_mode_override' => FALSE,
          ],
        ],
      ],
    ])->save();

    $this->drupalCreateContentType(['type' => 'blog']);

    // Note that media_install() grants 'view media' to all users by default.
    $user = $this->drupalCreateUser([
      'use text format test_format',
      'access media overview',
      'create blog content',
    ]);

    // Create a sample media entity to be embedded.
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);
    $file1 = File::create([
      'uri' => $this->getTestFiles('image')[0]->uri,
    ]);
    $file1->save();
    $file2 = File::create([
      'uri' => $this->getTestFiles('image')[1]->uri,
    ]);
    $file2->save();
    Media::create([
      'bundle' => 'image',
      'name' => 'Fear is the mind-killer',
      'field_media_image' => [
        [
          'target_id' => $file1->id(),
          'alt' => 'default alt',
          'title' => 'default title',
        ],
      ],
    ])->save();
    Media::create([
      'bundle' => 'image',
      'name' => 'Action cures fear',
      'field_media_image' => [
        [
          'target_id' => $file2->id(),
          'alt' => 'default alt',
          'title' => 'default title',
        ],
      ],
    ])->save();

    $this->drupalLogin($user);
  }

  /**
   * Tests pager navigation in the media library modal.
   */
  #[DataProvider('pagerInModalTestData')]
  public function testPagerInModal(string $pageType): void {
    $view = View::load('media_library');
    $display = &$view->getDisplay('default');
    $display['display_options']['pager'] = [
      'type' => $pageType,
      'options' => [
        'items_per_page' => 1,
      ],
    ];
    $view->save();

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('/node/add/blog');
    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');

    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-modal #media-library-content'));

    $assert_session->elementTextContains('css', '.js-media-library-item', 'Fear is the mind-killer');
    $assert_session->elementTextNotContains('css', '.js-media-library-item', 'Action cures fear');

    $page->clickLink('Next page');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementTextNotContains('css', '.js-media-library-item', 'Fear is the mind-killer');
    $assert_session->elementTextContains('css', '.js-media-library-item', 'Action cures fear');
  }

  /**
   * Data provider for ::testPagerInModal() method.
   */
  public static function pagerInModalTestData(): array {
    return [
      ['full'],
      ['mini'],
    ];
  }

}