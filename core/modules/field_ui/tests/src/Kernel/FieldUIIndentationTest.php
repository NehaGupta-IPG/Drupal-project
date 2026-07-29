<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\Kernel;

use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests indentation on Field UI.
 */
#[Group('field_ui')]
#[RunTestsInSeparateProcesses]
class FieldUIIndentationTest extends KernelTestBase {

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
    'field',
    'field_ui',
    'field_ui_test',
    'filter',
    'node',
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
    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer content types',
      'administer node display',
    ]);
    $this->setCurrentUser($admin_user);

    // Create Basic page node type.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

  }

  /**
   * Tests that the indentation classes are present in the content type display settings.
   */
  public function testIndentation(): void {
    $this->drupalGet('admin/structure/types/manage/page/display/default');
    $this->assertSession()->responseContains('js-indentation indentation');
  }

}
