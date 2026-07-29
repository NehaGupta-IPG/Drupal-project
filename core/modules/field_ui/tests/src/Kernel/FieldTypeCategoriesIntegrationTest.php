<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests field UI integration with field type categories for loading libraries.
 */
#[Group('field_ui')]
#[RunTestsInSeparateProcesses]
class FieldTypeCategoriesIntegrationTest extends KernelTestBase {

  use ContentTypeCreationTrait {
    createContentType as drupalCreateContentType;
  }
  use UserCreationTrait {
    createUser as drupalCreateUser;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'field',
    'field_ui',
    'file',
    'filter',
    'link',
    'node',
    'options',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['field_ui', 'filter', 'node', 'system', 'user']);

    \Drupal::service(ThemeInstallerInterface::class)->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();

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
