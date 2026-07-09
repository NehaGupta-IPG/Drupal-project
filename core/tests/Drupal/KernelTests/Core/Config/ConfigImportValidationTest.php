<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Config;

use Drupal\Core\Config\ConfigImporterException;
use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that config schema violations are caught during import validation.
 *
 * The fixture contains:
 * - A module that is already installed (config_test): one simple config object
 *   and one config entity, both with schema violations.
 * - A module that is not yet installed (filter): one simple config object and
 *   one config entity, both with schema violations. filter is used because it
 *   depends only on user (already in $modules), has no content-entity schema
 *   to set up, and provides both a simple config object (filter.settings) and
 *   a config entity type (filter.format.*).
 *
 * The test asserts that the import is rejected due to these violations.
 * Currently it fails — violations are not detected during import validation —
 * which proves the bug.
 */
#[Group('config')]
#[RunTestsInSeparateProcesses]
class ConfigImportValidationTest extends KernelTestBase {

  /**
   * Disable the test-level schema checker so only the importer's own
   * validation is under test.
   */
  protected bool $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_test', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['config_test', 'system']);

    $sync = $this->container->get('config.storage.sync');
    // Mirror active config into sync so the StorageComparer sees no changes by
    // default. Without this, every active config object would appear as a
    // pending deletion, masking the violations the test is about.
    $this->copyConfig($this->container->get('config.storage'), $sync);

    // Add filter (not yet installed) to the sync extension list.
    $extensions = $sync->read('core.extension');
    $extensions['module']['filter'] = 0;
    $sync->write('core.extension', $extensions);

    // Overlay the fixture files, which introduce schema violations for both
    // the already-installed module (config_test) and the not-yet-installed
    // module (filter).
    $fixture_storage = new FileStorage(__DIR__ . '/fixtures/config_import_validation');
    foreach ($fixture_storage->listAll() as $name) {
      $sync->write($name, $fixture_storage->read($name));
    }
  }

  /**
   * Schema violations in sync config should prevent a successful import.
   */
  public function testImportWithSchemaViolationsFails(): void {
    $this->expectException(ConfigImporterException::class);
    $this->configImporter()->import();
  }

}
