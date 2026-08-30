<?php

declare(strict_types=1);

namespace Drupal\Tests\inline_form_errors\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests inline_form_errors with a multiple required field.
 */
#[Group('inline_form_errors')]
#[RunTestsInSeparateProcesses]
class MultipleInlineFormErrorsTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_ui',
    'file',
    'inline_form_errors',
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

    $this->createContentType(['type' => 'page']);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_files',
      'entity_type' => 'node',
      'type' => 'file',
      'cardinality' => 5,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_files',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Files',
      'required' => TRUE,
    ]);
    $field->save();

    \Drupal::service('entity_display.repository')->getFormDisplay('node', 'page', 'default')
      ->setComponent('field_files', [
        'type' => 'file_generic',
      ])->save();

    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'page', 'default')
      ->setComponent('field_files', [
        'type' => 'file_default',
      ])->save();

    $this->drupalLogin($this->drupalCreateUser([
      'create page content',
      'edit own page content',
    ]));
  }

  /**
   * Tests number of inline form errors for limited multiple file widget.
   */
  #[DataProvider('themeProvider')]
  public function testInlineFormErrorsCount(string $theme): void {
    \Drupal::service('theme_installer')->install([$theme]);
    $this->config('system.theme')
      ->set('default', $theme)
      ->save();

    $this->drupalGet('node/add/page');
    $page = $this->getSession()->getPage();
    $page->fillField('Title', 'Test Node');
    $this->assertSession()->waitForElement('css', 'input[type="file"]');

    $page->pressButton('Save');
    $error_selector = '.form-item--error-message, .form-item__error-message';
    $this->assertSession()->waitForElementVisible('css', $error_selector);

    // The error must render exactly once.
    $errors = $page->findAll('css', $error_selector);
    $this->assertCount(1, $errors);
  }

  /**
   * Provides themes across both error-class variants.
   */
  public static function themeProvider(): array {
    return [
      'stark' => ['stark'],
      'olivero' => ['olivero'],
      'claro' => ['claro'],
      'default_admin' => ['default_admin'],
    ];
  }

}
