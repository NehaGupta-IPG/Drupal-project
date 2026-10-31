<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Recipe;

use Drupal\Core\Recipe\InvalidConfigException;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that config schema violations are caught when applying a recipe.
 *
 * The fixture recipe installs filter and applies four config objects with
 * schema violations. It uses filter for the same reason as
 * ConfigImportValidationTest: filter depends only on user, has no
 * content-entity schema to set up, and provides both a simple config object
 * (filter.settings) and a config entity type (filter.format.*).
 *
 * The test asserts that applying the recipe is rejected due to these violations.
 * Currently it fails — violations are not detected during recipe application —
 * which proves the bug.
 */
#[Group('Recipe')]
#[RunTestsInSeparateProcesses]
class RecipeSchemaViolationTest extends KernelTestBase {

  /**
   * Disable the test-level schema checker so only recipe validation is tested.
   */
  protected bool $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_test', 'system', 'user'];

  /**
   * Schema violations in recipe config should prevent a successful apply.
   */
  public function testRecipeWithSchemaViolationsFails(): void {
    $recipe = Recipe::createFromDirectory($this->root . '/core/tests/fixtures/recipes/config_schema_violation');
    $this->expectException(InvalidConfigException::class);
    RecipeRunner::processRecipe($recipe);
  }

}
