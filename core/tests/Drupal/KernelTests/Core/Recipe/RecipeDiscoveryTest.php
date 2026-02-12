<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Recipe;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Component\FileSystem\FileSystem;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeDiscovery;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * @coversDefaultClass \Drupal\Core\Recipe\RecipeDiscovery
 * @group Recipe
 */
class RecipeDiscoveryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'system'];

  /**
   * @testWith [true, true]
   *   [true, false]
   *   [false, true]
   */
  public function testFindRecipes(bool $include_test_recipes, bool $include_core_recipes): void {
    $this->installConfig(['system', 'user']);
    $drupal_root = $this->getDrupalRoot();

    $dir = $include_test_recipes
      ? $drupal_root . '/core/tests/fixtures/recipes'
      : NULL;

    // Certain test recipes have validation errors on purpose, but we want to
    // skip those.
    $discovery = new RecipeDiscovery($dir, $include_core_recipes, TRUE);
    $logger = new TestLogger();
    $discovery->setLogger($logger);

    $paths = array_map(
      fn (Recipe $recipe) => $recipe->path,
      iterator_to_array($discovery),
    );

    $test_recipes = preg_grep('/\/core\/tests\/fixtures\/recipes\//', $paths);
    if ($include_test_recipes) {
      $this->assertNotEmpty($test_recipes);

      // An invalid recipe should have been skipped.
      $invalid_recipes = preg_grep('/\/config_rollback_exception$/', $paths);
      $this->assertEmpty($invalid_recipes);
      $this->assertTrue($logger->hasWarningThatContains("Validation errors were found in $drupal_root/core/tests/fixtures/recipes/config_rollback_exception/recipe.yml:"));
    }
    else {
      $this->assertEmpty($test_recipes);
    }

    $core_recipes = preg_grep('/\/core\/recipes\//', $paths);
    if ($include_core_recipes) {
      $this->assertNotEmpty($core_recipes);
    }
    else {
      $this->assertEmpty($core_recipes);
    }
  }

  public function testDiscoveryFollowsSymlinks(): void {
    $cookbook_dir = FileSystem::getOsTemporaryDirectory();
    $link_name = uniqid($cookbook_dir . '/recipe_link');
    $target = $this->getDrupalRoot() . '/core/recipes/administrator_role';

    $file_system = new SymfonyFilesystem();
    $file_system->symlink($target, $link_name);

    $recipes = iterator_to_array(new RecipeDiscovery($cookbook_dir, FALSE));
    $this->assertCount(1, $recipes);
    $this->assertSame('Administrator role', reset($recipes)->name);

    $file_system->remove($link_name);
  }

}
