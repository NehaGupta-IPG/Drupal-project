<?php

declare(strict_types=1);

namespace Drupal\Tests\ckeditor5\FunctionalJavascript;

use Drupal\ckeditor5\Plugin\Editor\CKEditor5;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Tests emphasis in CKEditor 5.
 *
 * CKEditor's use of <i> is converted to <em> in Drupal, so additional coverage
 * is provided here to verify successful conversion.
 *
 * @internal
 */
#[Group('ckeditor5')]
#[RunTestsInSeparateProcesses]
class EmphasisTest extends WebDriverTestBase {
  use CKEditor5TestTrait;

  /**
   * The user to use during testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A host entity with a body field to use the <em> tag in.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $host;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ckeditor5',
    'node',
    'text',
    'filter',
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

    // Initiate filtered test format:
    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'Test format',
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<p> <br> <em>',
          ],
        ],
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
            'italic',
            'sourceEditing',
          ],
        ],
        'plugins' => [
          'ckeditor5_sourceEditing' => [
            'allowed_tags' => [],
          ],
        ],
      ],
    ])->save();

    // Initiate unfiltered test format:
    FilterFormat::create([
      'format' => 'test_format_unfiltered',
      'name' => 'Test format unfiltered',
      'filters' => [
        'filter_html' => [
          'status' => FALSE,
          'settings' => [
            'allowed_html' => '<p> <br> <em>',
          ],
        ],
      ],
    ])->save();
    Editor::create([
      'editor' => 'ckeditor5',
      'format' => 'test_format_unfiltered',
      'settings' => [
        'toolbar' => [
          'items' => [
            'italic',
            'sourceEditing',
          ],
        ],
        'plugins' => [
          'ckeditor5_sourceEditing' => [
            'allowed_tags' => [],
          ],
        ],
      ],
    ])->save();

    $this->assertSame([], array_map(
      function (ConstraintViolationInterface $v): string {
        return (string) $v->getMessage();
      },
      iterator_to_array(CKEditor5::validatePair(
        Editor::load('test_format'),
        FilterFormat::load('test_format')
      ))
    ));
    $this->adminUser = $this->drupalCreateUser([]);
    $this->adminUser->addRole($this->createAdminRole('admin', 'admin'));
    $this->adminUser->save();

    $this->drupalCreateContentType(['type' => 'blog']);
    $this->host = $this->createNode([
      'type' => 'blog',
      'title' => 'Animals with strange names',
      'body' => [
        'value' => '<p>This is a <em>test!</em></p>',
        'format' => 'test_format',
      ],
    ]);
    $this->host->save();

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Ensures that CKEditor italic model is converted to em.
   */
  public function testEmphasis(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet($this->host->toUrl('edit-form'));
    $this->waitForEditor();

    $emphasis_element = $assert_session->waitForElementVisible('css', '.ck-content p em');
    $this->assertEquals('test!', $emphasis_element->getText());

    $xpath = new \DOMXPath($this->getEditorDataAsDom());
    $emphasis_source = $xpath->query('//p/em');
    $this->assertNotEmpty($emphasis_source);
    $this->assertEquals('test!', $emphasis_source[0]->textContent);
    $page->pressButton('Save');

    $assert_session->responseContains('<p>This is a <em>test!</em></p>');
  }

  /**
   * Ensures that CKEditor doesn't add random em's.
   */
  public function testEmphasisWithoutFilterHtml() {
    $this->host = $this->createNode([
      'type' => 'blog',
      'title' => 'Animals with strange names',
      'body' => [
        'value' => '<i class="test">Test</i>',
        'format' => 'test_format_unfiltered',
      ],
    ]);
    $this->host->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet($this->host->toUrl('edit-form'));
    $this->waitForEditor();

    $emphasis_element = $assert_session->waitForElementVisible('css', '.ck-content i');
    $this->assertEquals('Test', $emphasis_element->getText());

    $xpath = new \DOMXPath($this->getEditorDataAsDom());
    $emphasis_source = $xpath->query('//i');
    $this->assertNotEmpty($emphasis_source);
    $this->assertEquals('Test', $emphasis_source[0]->textContent);
    $page->pressButton('Save');

    // Check, that the icon is generally present in the response:
    $assert_session->responseContains('<i class="test">Test</i>');
    // Check, that the icon is NOT wrapped in an em tag:
    $assert_session->responseNotContains('<em><i class="test">Test</i></em>');
  }

  /**
   * Tests that arbitrary attributes are allowed via GHS.
   */
  public function testEmphasisArbitraryHtml(): void {
    $assert_session = $this->assertSession();
    $editor = Editor::load('test_format');
    $settings = $editor->getSettings();

    // Allow the data-foo attribute in img via GHS.
    $settings['plugins']['ckeditor5_sourceEditing']['allowed_tags'] = ['<em data-foo>'];
    $editor->setSettings($settings);
    $editor->save();

    // Add data-foo use to an existing em tag.
    $original_value = $this->host->body->value;
    $this->host->body->value = str_replace('<em>', '<em data-foo="bar">', $original_value);
    $this->host->save();
    $this->drupalGet($this->host->toUrl('edit-form'));
    $this->waitForEditor();

    $emphasis_element = $assert_session->waitForElementVisible('css', '.ck-content p em');
    $this->assertEquals('bar', $emphasis_element->getAttribute('data-foo'));

    $xpath = new \DOMXPath($this->getEditorDataAsDom());
    $this->assertNotEmpty($xpath->query('//em[@data-foo="bar"]'));
  }

}
