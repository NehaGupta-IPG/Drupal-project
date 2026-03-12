<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path for making empty field text translatable.
 *
 * @see views_post_update_make_empty_field_text_translatable()
 */
#[Group('Update')]
#[CoversFunction('views_post_update_make_empty_field_text_translatable')]
#[RunTestsInSeparateProcesses]
final class MakeEmptyFieldTextTranslatableUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../../../system/tests/fixtures/update/uninstall-history.php',
    ];
  }

  /**
   * Tests that the empty field text config schema is translatable after update.
   */
  public function testEmptyFieldTextIsTranslatable(): void {
    $this->runUpdates();

    // Verify the post-update hook was actually invoked by the update system.
    $existing_updates = \Drupal::keyValue('post_update')->get('existing_updates', []);
    $this->assertContains('views_post_update_make_empty_field_text_translatable', $existing_updates);

    // After the hook runs, Drupal flushes all caches. The config schema is
    // reloaded from the updated YAML, where 'empty' is now type 'text'
    // (translatable) instead of 'string' (non-translatable).
    $definition = \Drupal::service('config.typed')->getDefinition('views_field');
    $this->assertSame('text', $definition['mapping']['empty']['type']);
  }

}
