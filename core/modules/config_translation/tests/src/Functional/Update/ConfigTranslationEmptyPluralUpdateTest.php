<?php

declare(strict_types=1);

namespace Drupal\Tests\config_translation\Functional\Update;

use Drupal\Component\Gettext\PoItem;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the removal of plural configuration translations without text.
 *
 * @see config_translation_post_update_remove_empty_plural_translations()
 */
#[Group('Update')]
#[Group('config_translation')]
#[CoversFunction('config_translation_post_update_remove_empty_plural_translations')]
#[RunTestsInSeparateProcesses]
class ConfigTranslationEmptyPluralUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/config-translation-empty-plural.php',
    ];
  }

  /**
   * Tests that the update removes only the translations without text.
   */
  public function testRemoveEmptyPluralTranslations(): void {
    $override = fn (string $name) => $this->container->get('language.config_factory_override')->getOverride('es', $name);
    $plural = 'display.default.display_options.fields.count.format_plural_string';
    $this->assertSame(PoItem::DELIMITER, $override('views.view.files')->get($plural));
    $label = $override('views.view.files')->get('label');
    $this->assertNotEmpty($label);
    $this->assertFalse($override('views.view.watchdog')->isNew());

    $this->runUpdates();

    $this->assertNull($override('views.view.files')->get($plural));
    $this->assertSame($label, $override('views.view.files')->get('label'));
    $this->assertTrue($override('views.view.watchdog')->isNew());
  }

}
