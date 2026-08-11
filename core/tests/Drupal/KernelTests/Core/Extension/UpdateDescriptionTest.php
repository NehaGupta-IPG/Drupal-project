<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Extension;

use Drupal\Core\Update\DatabaseUpdate;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for update descriptions.
 */
#[Group('Core')]
#[RunTestsInSeparateProcesses]
class UpdateDescriptionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['update_test_description'];

  /**
   * Tests the list of pending database updates.
   *
   * @see \Drupal\Core\Update\DatabaseUpdate::getUpdateList()
   */
  public function testUpdateGetUpdateList(): void {
    \Drupal::service('update.update_hook_registry')->setInstalledVersion('update_test_description', 8000);
    \Drupal::moduleHandler()->loadInclude('update_test_description', 'install');

    $updates = \Drupal::service(DatabaseUpdate::class)->getUpdateList();
    $expected = [
      'pending' => [
        8001 => '8001 - Update test of slash in description and/or.',
        8002 => '8002 - Update test with multiline description, the quick brown fox jumped over the lazy dog.',
      ],
      'start' => 8001,
    ];
    $this->assertEquals($expected, $updates['update_test_description']);
  }

}
