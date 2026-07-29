<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests field UI integration with field type categories for loading libraries.
 */
#[Group('field_ui')]
#[RunTestsInSeparateProcesses]
class FieldTypeCategoriesIntegrationTest extends FieldUiKernelTestBase {

  use ContentTypeCreationTrait {
    createContentType as drupalCreateContentType;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'file',
    'filter',
    'link',
    'node',
    'options',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installConfig(['filter', 'node']);

    // Create a test user.
    $admin_user = $this->drupalCreateUser(['administer node fields']);
    $this->setCurrentUser($admin_user);
  }

  /**
   * Tests if the libraries are loaded on FieldStorageAddForm.
   */
  public function testLibrariesLoaded(): void {
    $this->drupalGet('admin/structure/types/manage/' . $this->drupalCreateContentType()->id() . '/fields/add-field');
    $settings = $this->getDrupalSettings();
    $css_libraries = [
      'file/drupal.file-icon',
      'text/drupal.text-icon',
      'options/drupal.options-icon',
      'comment/drupal.comment-icon',
      'link/drupal.link-icon',
    ];
    $libraries = explode(',', $settings['ajaxPageState']['libraries']);
    foreach ($css_libraries as $css_library) {
      $this->assertContains($css_library, $libraries);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getDrupalSettings(): array {
    $script = $this->assertSession()->elementExists('css', 'script[data-drupal-selector="drupal-settings-json"]');
    $settings = Json::decode($script->getText());
    if (isset($settings['ajaxPageState']['libraries'])) {
      $settings['ajaxPageState']['libraries'] = UrlHelper::uncompressQueryParameter($settings['ajaxPageState']['libraries']);
    }
    return $settings;
  }

}
