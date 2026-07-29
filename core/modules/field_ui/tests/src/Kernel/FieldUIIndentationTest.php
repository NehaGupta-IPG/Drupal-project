<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\Kernel;

use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests indentation on Field UI.
 */
#[Group('field_ui')]
#[RunTestsInSeparateProcesses]
class FieldUIIndentationTest extends FieldUiKernelTestBase {

  use ContentTypeCreationTrait {
    createContentType as drupalCreateContentType;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_ui_test', 'filter', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installConfig(['filter', 'node']);

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
