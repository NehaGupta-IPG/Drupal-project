<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests reconciling configuration langcode with its translatability.
 *
 * @see system_post_update_reconcile_translatable_config_langcode()
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class ReconcileTranslatableConfigLangcodeUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests that langcode is added to, and removed from, existing config.
   */
  public function testReconcileTranslatableConfigLangcode(): void {
    // Write directly to storage, bypassing Config::save(), so the fixture
    // represents configuration exactly as it would be found on a site that
    // has never run this update: one with a translatable value but no
    // langcode of its own, and one with a langcode but no translatable
    // value.
    $storage = \Drupal::service('config.storage');

    // user.settings has a translatable 'anonymous' value.
    $data = $storage->read('user.settings');
    $this->assertArrayHasKey('anonymous', $data);
    unset($data['langcode']);
    $storage->write('user.settings', $data);

    // system.cron has no translatable value.
    $data = $storage->read('system.cron');
    $this->assertArrayNotHasKey('anonymous', $data);
    $data['langcode'] = 'en';
    $storage->write('system.cron', $data);

    $this->runUpdates();

    $this->assertSame('en', \Drupal::config('user.settings')->get('langcode'));
    $this->assertArrayNotHasKey('langcode', \Drupal::config('system.cron')->getRawData());
  }

}
