<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the media library UI access.
 */
#[Group('media_library')]
#[RunTestsInSeparateProcesses]
class WidgetAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['media_library_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the widget with a required field that the user can't access.
   */
  public function testRequiredFieldNoAccess(): void {
    // Make field_single_media_type required.
    $fieldConfig = FieldConfig::loadByName('node', 'basic_page', 'field_single_media_type');
    assert($fieldConfig instanceof FieldConfig);
    $fieldConfig->setRequired(TRUE)
      ->save();

    // Deny access to the field.
    \Drupal::state()->set('media_library_test_entity_field_access_deny_fields', ['field_single_media_type']);

    $user = $this->drupalCreateUser([
      'access administration pages',
      'access content',
      'create basic_page content',
      'create type_one media',
      'view media',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('node/add/basic_page');

    $this->assertSession()->elementNotExists('css', '.field--name-field-single-media-type');

    $this->submitForm([
      'title[0][value]' => $this->randomMachineName(),
    ], 'Save');

    $this->assertSession()->elementNotExists('css', '.messages--error');
    $this->assertSession()->pageTextNotContains('Single media type field is required.');
  }

}
