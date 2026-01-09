<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnType;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Table;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the installer with database errors.
 */
#[Group('Installer')]
#[RunTestsInSeparateProcesses]
class InstallerDatabaseErrorMessagesTest extends InstallerTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUpSettings(): void {
    // We are creating a table here to force an error in the installer because
    // it will try and create the drupal_install_test table as this is part of
    // the standard database tests performed by the installer in
    // Drupal\Core\Database\Install\Tasks.
    Database::getConnection('default')->schema()->createTableFromDefinition(
      new Table(
        name: 'drupal_install_test',
        columns: [
          new Column(
            name: 'id',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
        ],
        primaryKey: new PrimaryKey(['id']),
      ),
    );
    parent::setUpSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpSite(): void {
    // This step should not appear as we had a failure on the settings screen.
  }

  /**
   * Verifies that the error message in the settings step is correct.
   */
  public function testSetUpSettingsErrorMessage(): void {
    $this->assertSession()->responseContains('<ul><li>Failed to <strong>CREATE</strong> a test table');
  }

}
