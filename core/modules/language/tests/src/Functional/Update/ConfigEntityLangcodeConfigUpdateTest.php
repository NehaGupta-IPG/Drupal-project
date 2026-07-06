<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests population of config entity langcode visibility on update.
 */
#[Group('language')]
#[RunTestsInSeparateProcesses]
class ConfigEntityLangcodeConfigUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../../modules/system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests language_update_11500().
   *
   * @see language_update_11500()
   */
  public function testConfigEntityLangcodeSettingsArePopulatedOnUpdate(): void {
    // Simulate an existing pre-11500 site where Language is already enabled.
    \Drupal::service('module_installer')->install(['language']);
    \Drupal::service('update.update_hook_registry')->setInstalledVersion('language', 11400);

    $this->assertSame([], $this->config('language.config_type_settings')->get('show_langcode'));

    $this->runUpdates();

    $config = $this->config('language.config_type_settings');
    foreach (['date_format', 'menu', 'taxonomy_vocabulary', 'view'] as $entity_type_id) {
      $this->assertTrue((bool) $config->get('show_langcode.' . $entity_type_id));
    }
  }

}
