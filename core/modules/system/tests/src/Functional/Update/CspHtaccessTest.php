<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\Core\File\FileSystemInterface;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path for the CSP header in .htaccess update.
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class CspHtaccessTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests the upgrade path for adding a CSP header to the .htaccess file.
   */
  public function testRunUpdates(): void {
    $htaccessWriter = \Drupal::service('file.htaccess_writer');
    $fileSystem = \Drupal::service('file_system');
    assert($fileSystem instanceof FileSystemInterface);
    foreach ($htaccessWriter->defaultProtectedDirs() as $dir) {
      $path = $fileSystem->realpath($dir->getPath() . '/.htaccess');
      // Simulate previous version of the file without the CSP header.
      file_put_contents($path, 'Options -Indexes -ExecCGI -Includes -MultiViews');
      $this->assertStringNotContainsString(
        'Header set Content-Security-Policy',
        file_get_contents($path)
      );
    }
    $this->runUpdates();
    foreach ($htaccessWriter->defaultProtectedDirs() as $dir) {
      $this->assertStringContainsString(
        'Header set Content-Security-Policy',
        file_get_contents($fileSystem->realpath($dir->getPath() . '/.htaccess'))
      );
    }
  }

}
