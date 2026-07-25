<?php

declare(strict_types=1);

namespace Drupal\BuildTests\Composer\Component;

use Drupal\BuildTests\Composer\ComposerBuildTestBase;
use Drupal\Composer\Composer;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Demonstrate that the Component generator responds to release tagging.
 */
#[CoversNothing]
#[Group('Composer')]
#[Group('Component')]
class ComponentsTaggedReleaseTest extends ComposerBuildTestBase {

  /**
   * Highly arbitrary version and constraint expectations.
   *
   * @return array
   *   - First element is the tag that should be applied to \Drupal::version.
   *   - Second element is the resulting constraint which should be present in
   *     the component core dependencies.
   */
  public static function providerVersionConstraint(): array {
    return [
      // [Tag, constraint]
      '1.0.x-dev' => ['1.0.x-dev', '1.0.x-dev'],
      '1.0.0-beta1' => ['1.0.0-beta1', '1.0.0-beta1'],
      '1.0.0-rc1' => ['1.0.0-rc1', '1.0.0-rc1'],
      '1.0.0' => ['1.0.0', '^1.0'],
    ];
  }

  /**
   * Validate release tagging and regeneration of dependencies.
   */
  #[DataProvider('providerVersionConstraint')]
  public function testReleaseTagging(string $tag, string $constraint): void {
    $this->copyCodebase();
    $drupal_root = $this->getWorkspaceDirectory();

    // The daffie/pgsql package pins drupal/core to a real core version range,
    // so it cannot coexist with the arbitrary tags applied below. It is an
    // external dependency unrelated to the component release tagging being
    // tested, so remove it before emulating the release script.
    $this->removePgsqlRequirement($drupal_root);

    // Set the core version.
    Composer::setDrupalVersion($drupal_root, $tag);
    $this->assertDrupalVersion($tag, $drupal_root);

    // Emulate the release script.
    // @see https://github.com/xjm/drupal_core_release/blob/main/tag.sh
    $this->executeCommand("COMPOSER_ROOT_VERSION=\"$tag\" COMPOSER_NO_SECURITY_BLOCKING=1 composer update drupal/core*");
    $this->assertCommandSuccessful();
    $this->assertErrorOutputContains('generateComponentPackages');

    // Find all the components.
    $component_finder = $this->getComponentPathsFinder($drupal_root);

    // Loop through all the component packages.
    /** @var \Symfony\Component\Finder\SplFileInfo $composer_json */
    foreach ($component_finder->getIterator() as $composer_json) {
      $composer_json_data = json_decode(file_get_contents($composer_json->getPathname()), TRUE);
      $requires = array_merge(
        $composer_json_data['require'] ?? [],
        $composer_json_data['require-dev'] ?? []
      );
      // Required packages from drupal/core-* should have our constraint.
      foreach ($requires as $package => $req_constraint) {
        if (str_contains($package, 'drupal/core-')) {
          $this->assertEquals($constraint, $req_constraint);
        }
      }
    }
  }

  /**
   * Removes the daffie/pgsql requirement from the workspace composer.json.
   *
   * @param string $drupal_root
   *   The Drupal root directory.
   */
  protected function removePgsqlRequirement(string $drupal_root): void {
    $composer_json_path = $drupal_root . '/composer.json';
    $composer_json_data = json_decode(file_get_contents($composer_json_path), TRUE);
    unset($composer_json_data['require']['daffie/pgsql']);
    unset($composer_json_data['config']['allow-plugins']['daffie/drupal-core-pgsql-module-remover']);
    file_put_contents($composer_json_path, json_encode($composer_json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
  }

}
