<?php

declare(strict_types=1);

namespace Drupal\BuildTests\Command;

use Drupal\BuildTests\Framework\BuildTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Tests running the CLI scripts directly from a web/ docroot.
 *
 * In projects created from drupal/recommended-project, the vendor directory is
 * next to the Drupal root, not inside it. Running core/scripts/dr directly,
 * instead of through vendor/bin/dr, must still find the autoloader.
 */
#[Group('Command')]
class DrScriptTest extends BuildTestBase {

  /**
   * Data provider for testDirectCallFromWebDocroot().
   *
   * @return array<string, array{string}>
   *   The scripts to test.
   */
  public static function providerScripts(): array {
    return [
      'dr' => ['dr'],
      'drupal' => ['drupal'],
    ];
  }

  /**
   * Tests calling a CLI script directly when the Drupal root is in web/.
   *
   * @param string $script
   *   The name of the script in core/scripts.
   */
  #[DataProvider('providerScripts')]
  public function testDirectCallFromWebDocroot(string $script): void {
    // Build a small project with the Drupal root in web/, and only the files
    // the scripts need. Like the scaffold plugin does for a real project, the
    // autoload files in web/ point to the vendor directory outside of it.
    $vendor = $this->getComposerRoot() . '/vendor';
    $web = $this->getWorkspaceDirectory() . '/web';
    $fs = new Filesystem();
    foreach (['dr', 'drupal'] as $file) {
      $fs->copy($this->getDrupalRoot() . "/core/scripts/$file", "$web/core/scripts/$file");
    }
    $fs->dumpFile("$web/autoload.php", "<?php\n\nreturn require '$vendor/autoload.php';\n");
    $fs->dumpFile("$web/autoload_runtime.php", "<?php\n\n\$_ENV['APP_RUNTIME'] ??= \\Drupal\\Core\\Runtime\\DrupalRuntime::class;\nreturn require '$vendor/autoload_runtime.php';\n");

    $php = (new PhpExecutableFinder())->find();
    $this->executeCommand(escapeshellarg($php) . " core/scripts/$script list", 'web');
    $this->assertCommandSuccessful();
    $this->assertCommandOutputContains('Available commands:');
  }

}
