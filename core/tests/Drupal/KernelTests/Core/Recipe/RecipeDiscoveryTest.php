<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Recipe;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Component\FileSystem\FileSystem;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeDiscovery;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * Tests recipe discovery.
 */
#[CoversClass(RecipeDiscovery::class)]
#[Group('Recipe')]
#[RunTestsInSeparateProcesses]
class RecipeDiscoveryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'system'];

  /**
   * Tests finding recipes.
   *
   * @param bool $include_test_recipes
   *   Whether to include test recipes.
   * @param bool $include_core_recipes
   *   Whether to include core recipes.
   */
  #[TestWith([TRUE, TRUE])]
  #[TestWith([TRUE, FALSE])]
  #[TestWith([FALSE, TRUE])]
  public function testFindRecipes(bool $include_test_recipes, bool $include_core_recipes): void {
    $this->installConfig(['system', 'user']);
    $drupal_root = $this->root;

    $dir = $include_test_recipes
      ? $drupal_root . '/core/tests/fixtures/recipes'
      : NULL;
    // Certain test recipes have validation errors on purpose, but we want to
    // skip those.
    $skip_invalid = TRUE;
    // Certain test recipes are broken on purpose, but we want to skip those as
    // well.
    $broken_recipes = [
      'broken_enum_action_test/',
    ];
    $discovery = new RecipeDiscovery($dir, $include_core_recipes, $skip_invalid, $broken_recipes);
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
      $invalid_recipes = preg_grep('/\/invalid_recipe$/', $paths);
      $this->assertEmpty($invalid_recipes);
      $this->assertTrue($logger->hasWarningThatContains("Validation errors were found in $drupal_root/core/tests/fixtures/recipes/invalid_recipe/recipe.yml:"));

      // A specified path should have been excluded.
      $excluded_paths = preg_grep('/\/broken_enum_action_test$/', $paths);
      $this->assertEmpty($excluded_paths);
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

  /**
   * Tests following symlinks in recipe discovery.
   */
  public function testDiscoveryFollowsSymlinks(): void {
    $cookbook_dir = FileSystem::getOsTemporaryDirectory();
    $link_name = uniqid($cookbook_dir . '/recipe_link');
    $target = $this->root . '/core/recipes/administrator_role';

    $file_system = new SymfonyFilesystem();
    $file_system->symlink($target, $link_name);

    $recipes = iterator_to_array(new RecipeDiscovery($cookbook_dir, FALSE));
    $this->assertCount(1, $recipes);
    $this->assertSame('Administrator role', reset($recipes)->name);

    $file_system->remove($link_name);
  }

}
