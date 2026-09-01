<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Config;

use Drupal\Core\Config\ConfigImporterException;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the deletion of a configuration entity that others depend on.
 *
 * Covers three related behaviours: enforced dependencies are validated during
 * import, deletes are processed dependents-first, and the entity API heals
 * dependents while the importer blocks instead.
 *
 * @see \Drupal\Core\EventSubscriber\ConfigImportSubscriber::validateDependencies()
 * @see \Drupal\Core\Config\StorageComparer::addChangelistDelete()
 * @see \Drupal\Core\Config\Entity\ConfigEntityBase::preDelete()
 */
#[Group('config')]
#[RunTestsInSeparateProcesses]
class ConfigImporterEnforcedDependencyTest extends KernelTestBase {

  /**
   * The name of the configuration that other configuration depends on.
   */
  const DEPENDENCY = 'config_test.dynamic.alpha';

  /**
   * The name of the configuration depending on static::DEPENDENCY.
   */
  const DEPENDENT = 'config_test.dynamic.beta';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_test', 'system', 'config_import_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'config_test']);
    // Installing config_test's default configuration pollutes the global
    // variable used for recording hook invocations, so clear it out manually.
    unset($GLOBALS['hook_config_test']);

    $this->copyConfig($this->container->get('config.storage'), $this->container->get('config.storage.sync'));
  }

  /**
   * Tests that a dependency on the deleted configuration fails validation.
   *
   * The dependent is retained in sync storage while its dependency is removed
   * from it. That is an error whichever key the dependency is declared under,
   * since every other consumer of the dependency graph merges enforced
   * dependencies into the dependency list.
   *
   * @param string $dependency_key
   *   Either 'enforced' or 'normal'.
   *
   * @see \Drupal\Core\Config\Entity\ConfigEntityDependency::__construct()
   */
  #[DataProvider('providerDependencyIsValidated')]
  public function testDependencyIsValidated(string $dependency_key): void {
    $this->createFixture($dependency_key);
    $this->container->get('config.storage.sync')->delete(static::DEPENDENCY);

    $config_importer = $this->configImporter();
    try {
      $config_importer->import();
      $this->fail('ConfigImporterException not thrown; a retained dependent was not validated against the deleted configuration.');
    }
    catch (ConfigImporterException) {
      $expected = 'Configuration <em class="placeholder">' . static::DEPENDENT . '</em> depends on the <em class="placeholder">' . static::DEPENDENCY . '</em> configuration that will not exist after import.';
      $this->assertContainsEquals($expected, $config_importer->getErrors());
    }
  }

  /**
   * Data provider for testDependencyIsValidated().
   *
   * @return array[]
   *   The key of the dependency array the dependency is declared under.
   */
  public static function providerDependencyIsValidated(): array {
    return [
      'enforced' => ['enforced'],
      'normal' => ['normal'],
    ];
  }

  /**
   * Tests that to delete changelist lists dependents before dependencies.
   *
   * When both the dependency and its dependent are removed from sync storage,
   * the dependent is deleted first, so by the time the dependency is deleted,
   * there is no dependent left to react to its removal.
   */
  public function testDeleteChangelistOrderIsDependentsFirst(): void {
    $this->createFixture('enforced');
    $sync = $this->container->get('config.storage.sync');
    $sync->delete(static::DEPENDENCY);
    $sync->delete(static::DEPENDENT);

    $deletes = $this->configImporter()->getUnprocessedConfiguration('delete');
    $this->assertSame([static::DEPENDENT, static::DEPENDENCY], array_values($deletes));
  }

  /**
   * Tests that deleting via the entity API heals dependents, but import does not.
   *
   * The asymmetry is intended. Sync storage asserts what the dependent should
   * contain, so healing it during import would be undone by the next import.
   * The entity API has no such source of truth and heals instead.
   */
  public function testEntityApiDeleteHealsButImportDoesNot(): void {
    $this->createFixture('normal');
    $state = $this->container->get('state');
    $this->assertSame([], $state->get('config_test.on_dependency_removal_called', []));

    // Importing the deletion is blocked by validation, and no dependent is
    // asked to remove its dependency.
    $this->container->get('config.storage.sync')->delete(static::DEPENDENCY);
    try {
      $this->configImporter()->import();
      $this->fail('ConfigImporterException not thrown; the import was not blocked.');
    }
    catch (ConfigImporterException) {
      $this->assertSame([], $state->get('config_test.on_dependency_removal_called', []));
    }

    // The import aborted during validation, so the dependency still exists in
    // active configuration. Deleting it through the entity API instead does
    // reach the dependent.
    $storage = $this->container->get('entity_type.manager')->getStorage('config_test');
    $storage->load('alpha')->delete();

    $called = $state->get('config_test.on_dependency_removal_called', []);
    $this->assertSame(['beta'], array_keys($called));
    $this->assertSame([static::DEPENDENCY], $called['beta']['config']);
  }

  /**
   * Creates a dependency and a dependent in both active and sync storage.
   *
   * The entity IDs 'primary', 'dependency' and 'dependent' are avoided because
   * they carry postSave() and postDelete() side effects.
   *
   * @param string $dependency_key
   *   Either 'enforced' to declare the dependency under
   *   dependencies.enforced.config, or 'normal' to declare it under
   *   dependencies.config.
   *
   * @see \Drupal\config_test\Entity\ConfigTest::postSave()
   * @see \Drupal\config_test\Entity\ConfigTest::postDelete()
   */
  protected function createFixture(string $dependency_key): void {
    $active = $this->container->get('config.storage');
    $entity_storage = $this->container->get('entity_type.manager')->getStorage('config_test');

    $entity_storage->create(['id' => 'alpha', 'label' => 'Alpha'])->save();

    $dependent = $entity_storage->create(['id' => 'beta', 'label' => 'Beta']);
    if ($dependency_key === 'enforced') {
      $dependent->setEnforcedDependencies(['config' => [static::DEPENDENCY]]);
    }
    $dependent->save();

    if ($dependency_key === 'normal') {
      // calculateDependencies() discards everything but enforced dependencies
      // on every save, so a non-enforced dependency on another config entity
      // has to be written to the storage directly.
      $data = $active->read(static::DEPENDENT);
      $data['dependencies']['config'] = [static::DEPENDENCY];
      $active->write(static::DEPENDENT, $data);
    }

    $this->copyConfig($active, $this->container->get('config.storage.sync'));
  }

}
