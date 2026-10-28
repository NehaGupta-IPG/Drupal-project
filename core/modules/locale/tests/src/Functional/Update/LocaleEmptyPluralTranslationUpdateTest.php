<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Functional\Update;

use Drupal\Component\Gettext\PoItem;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the deletion of plural translations without text.
 *
 * @see locale_post_update_delete_empty_plural_translations()
 */
#[Group('Update')]
#[Group('locale')]
#[CoversFunction('locale_post_update_delete_empty_plural_translations')]
#[RunTestsInSeparateProcesses]
class LocaleEmptyPluralTranslationUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/locale-empty-plural-translation.php',
    ];
  }

  /**
   * Tests that the update deletes only the plural translation without text.
   */
  public function testDeleteEmptyPluralTranslations(): void {
    $translation = fn (string $singular) => $this->container->get('locale.storage')->findTranslation([
      'source' => $singular . PoItem::DELIMITER . $singular . 's',
      'language' => 'es',
    ])->translation;
    $this->assertSame(PoItem::DELIMITER, $translation('@count apple'));

    $this->runUpdates();

    $this->assertNull($translation('@count apple'));
    $this->assertSame('@count pear ES' . PoItem::DELIMITER . '@count pear ES', $translation('@count pear'));
  }

}
