<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Traits;

use Drupal\Core\Database\Database;
use Drupal\Core\Logger\RfcLogLevel;

/**
 * Contains helper methods for importing translations in tests.
 */
trait TranslationImportTestTrait {

  /**
   * Verifies that the results of a translation import were logged.
   *
   * @param int $translations_added
   *   The number of translations added.
   * @param int $translations_updated
   *   The number of translations updated.
   * @param int $translations_deleted
   *   The number of translations deleted.
   */
  protected function assertImport(int $translations_added, int $translations_updated, int $translations_deleted): void {
    $database = Database::getConnection();
    $this->assertNotEmpty($database->select('watchdog', 'w')
      ->condition('type', 'locale')
      ->condition('message', 'Translations imported: %number added, %update updated, %delete removed.')
      ->condition('variables', serialize([
        '%number' => $translations_added,
        '%update' => $translations_updated,
        '%delete' => $translations_deleted,
      ]))
      ->condition('severity', RfcLogLevel::NOTICE)
      ->countQuery()
      ->execute()
      ->fetchField()
    );

    // Truncate the table so to avoid assertions falsely passing on subsequent
    // translation imports.
    $database->truncate('watchdog')->execute();
  }

}
