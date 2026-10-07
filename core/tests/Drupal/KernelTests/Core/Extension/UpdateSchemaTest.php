<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Extension;

use Drupal\KernelTests\KernelTestBase;
use Drupal\TestTools\Attribute\ShareEnvironment;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for schema and update includes.
 */
#[Group('Core')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment]
class UpdateSchemaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['update_test_schema'];

  /**
   * {@inheritdoc}
   */
  protected function setUpEnvironment(): void {
    parent::setUpEnvironment();
    \Drupal::state()->set('update_test_schema_version', 8001);
    $this->installSchema('update_test_schema', ['update_test_schema_table']);
  }

  /**
   * Tests the function parses schema updates as integer numbers.
   *
   * @see \Drupal\Core\Update\UpdateHookRegistry::getAvailableUpdates()
   */
  public function testDrupalGetSchemaVersionsInt(): void {
    $schema = \Drupal::service('update.update_hook_registry')->getAvailableUpdates('update_test_schema');
    foreach ($schema as $version) {
      $this->assertIsInt($version);
    }
  }

}
