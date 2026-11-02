<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests removal of the obsolete bundle field map collection.
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class BundleFieldMapUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests that updates remove only the obsolete collection.
   */
  public function testRunUpdates(): void {
    $map = ['field_example' => ['type' => 'string', 'bundles' => ['page' => 'page']]];
    \Drupal::keyValue('entity.definitions.bundle_field_map')->set('node', $map);
    \Drupal::keyValue('bundle_field_map_update_test')->set('preserved', $map);

    $this->runUpdates();

    $this->assertSame([], \Drupal::keyValue('entity.definitions.bundle_field_map')->getAll());
    $this->assertSame($map, \Drupal::keyValue('bundle_field_map_update_test')->get('preserved'));
  }

}
