<?php

declare(strict_types=1);

namespace Drupal\Tests\Composer\Generator;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests DrupalCoreRecommendedBuilder.
 */
#[Group('Metapackage')]
class OverlapWithTopLevelDependenciesTest extends TestCase {

  /**
   * Tests top level and core-recommended dependencies do not overlap.
   */
  public function testOverlapWithTemplateProject(): void {
    $root = dirname(__DIR__, 6);
    // Read RecommendedProject template composer.json.
    $top_level_composer_json = json_decode(file_get_contents("$root/composer/Template/RecommendedProject/composer.json"), TRUE);

    // Read drupal/core-recommended composer.json.
    $core_recommended_composer_json = json_decode(file_get_contents("$root/composer/Metapackage/CoreRecommended/composer.json"), TRUE);

    // Fail if any required project in the 'require' section of the
    // RecommendedProject template also exists in core/recommended.
    foreach ($top_level_composer_json['require'] as $project => $version_constraint) {
      $this->assertArrayNotHasKey($project, $core_recommended_composer_json['require'], "Pinned project $project is also a top-level dependency of composer/Template/RecommendedProject. This can expose a Composer bug. See https://www.drupal.org/project/drupal/issues/3134648 and https://github.com/composer/composer/issues/8882");
    }
  }

}
