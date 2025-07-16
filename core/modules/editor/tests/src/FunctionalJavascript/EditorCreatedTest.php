<?php

declare(strict_types=1);

namespace Drupal\Tests\editor\FunctionalJavascript;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the editor:attached JS event.
 */
#[Group('editor')]
class EditorCreatedTest extends WebDriverTestBase {

  /**
   * The user to use during testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ckeditor5',
    'node',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a format without a CKEditor.
    FilterFormat::create([
      'format' => 'no_ckeditor',
      'name' => 'Plain text',
      'weight' => -1,
      'filters' => [
        'filter_html_escape' => [
          'status' => TRUE,
          'weight' => 0,
          'settings' => [],
        ],
      ],
    ])->save();

    // Create a format with CKEditor5.
    FilterFormat::create([
      'format' => 'ckeditor',
      'name' => 'Test format',
      'weight' => 0,
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'weight' => 0,
          'settings' => [
            'allowed_html' => '<p> <strong> <em>',
          ],
        ],
      ],
    ])->save();

    Editor::create([
      'format' => 'ckeditor',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => [
            'bold',
            'italic',
          ],
        ],
        'plugins' => [],
      ],
      'image_upload' => [
        'status' => FALSE,
      ],
    ])->save();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    $this->user = $this->drupalCreateUser([
      'administer filters',
      'create article content',
      'use text format no_ckeditor',
      'use text format ckeditor',
    ]);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests that the editor:attached JS event fires.
   */
  public function testEditorAttachedEvent(): void {
    // Load the node add form, starts with no_ckeditor format, so no editor.
    $this->drupalGet('node/add/article');
    $this->assertSession()->elementExists('css', 'textarea[name="body[0][value]"]');

    // Ensure no CKEditor instance yet.
    $this->assertSession()->elementNotExists('css', '.ck-editor__editable');

    // Add an event listener for 'editor:attached'.
    $setup_script = <<<JS
document.addEventListener('editor:attached', function(event) {
  const editor = event.detail[0];
  window.editorAttached = true;
  window.attachedEditorIsInstance = (typeof editor === 'object' && editor !== null && typeof editor.model !== 'undefined');
});
window.editorAttached = false;
window.attachedEditorIsInstance = false;
JS;
    $this->getSession()->executeScript($setup_script);

    // Switch to the format with CKEditor.
    $this->getSession()->getPage()->selectFieldOption('body[0][format]', 'ckeditor');

    // Wait for CKEditor5 instance to attach.
    $this->assertSession()->waitForElementVisible('css', '.ck-editor__editable');

    // Assert event was triggered.
    $this->assertJsCondition('window.editorAttached === true');

    // Assert passed editor is valid CKEditor5 instance.
    $this->assertJsCondition('window.attachedEditorIsInstance === true');
  }

}
