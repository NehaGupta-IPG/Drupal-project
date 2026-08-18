<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Recipe;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Component\FileSystem\FileSystem;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Recipe\Finder;
use Drupal\Core\Recipe\Recipe;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * Tests the recipe finder.
 */
#[CoversClass(Finder::class)]
#[Group('Recipe')]
#[RunTestsInSeparateProcesses]
class RecipeFinderTest extends KernelTestBase {

  /**
   * Tests that the finder yields Recipe objects.
   */
  public function testFinderYieldsRecipes(): void {
    foreach (new Finder($this->root . '/core/recipes') as $recipe) {
      $this->assertInstanceOf(Recipe::class, $recipe);
    }
  }

  /**
   * Tests that invalid recipes can be safely skipped, with a log message.
   */
  public function testSkipInvalidRecipe(): void {
    $finder = new Finder($this->root . '/core/tests/fixtures/recipes', TRUE);
    $finder->logger = new TestLogger();

    // Convert to an array so that the generator gets run.
    iterator_to_array($finder);
    $this->assertTrue($finder->logger->hasErrorThatContains('foo: !php/enum Drupal\config_enum_test\DoesNotExistEnumValue::No'));
  }

  /**
   * Tests that there is an error if Composer is misconfigured for recipes.
   */
  public function testUnknownComposerRecipeDirectory(): void {
    // By default, core's `composer.json` does not set up a recipe install path.
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("No recipe install location is configured in $this->root/composer.json.");
    Finder::getComposerRecipesDirectory();
  }

  /**
   * Tests that the Composer recipe directory is read correctly.
   */
  public function testComposerRecipeDirectory(): void {
    $temp_dir = FileSystem::getOsTemporaryDirectory() . '/test';
    mkdir($temp_dir . '/recipes/test', recursive: TRUE);

    $contents = Json::encode([
      'extra' => [
        'installer-paths' => [
          'recipes/{$name}' => ['type:' . Recipe::COMPOSER_PROJECT_TYPE],
        ],
      ],
    ]);
    file_put_contents($temp_dir . '/composer.json', $contents);

    $this->assertSame($temp_dir . '/recipes/{$name}', Finder::getComposerRecipesDirectory($temp_dir));
    // The project root is now statically cached, so if we put a recipe in
    // there, it should be discovered.
    file_put_contents($temp_dir . '/recipes/test/recipe.yml', 'name: Test!');
    $names = array_column(iterator_to_array(new Finder()), 'name');
    $this->assertContains('Test!', $names);

    (new SymfonyFilesystem())->remove($temp_dir);
  }

  /**
   * Tests that invalid recipes throw an exception by default.
   */
  public function testInvalidRecipesThrow(): void {
    $finder = new Finder($this->root . '/core/tests/fixtures/recipes');
    // This is a parse error, so we don't care about the exception type.
    $this->expectExceptionMessage('foo: !php/enum Drupal\config_enum_test\DoesNotExistEnumValue::No');
    iterator_to_array($finder);
  }

  /**
   * Tests scanning specific directories for recipes.
   */
  public function testScanSpecificDirectories(): void {
    // The finder can scan a single directory...
    $finder = new Finder($this->root . '/core/recipes', TRUE);
    $names = array_column(iterator_to_array($finder), 'name');
    $this->assertContains('Administrator role', $names);
    $this->assertContains('Editorial workflow', $names);
    $this->assertNotContains('Input Test', $names);

    // ...or several.
    $finder = new Finder([
      $this->root . '/core/recipes',
      $this->root . '/core/tests/fixtures/recipes',
    ], TRUE);
    $names = array_column(iterator_to_array($finder), 'name');
    $this->assertContains('Administrator role', $names);
    $this->assertContains('Editorial workflow', $names);
    $this->assertContains('Input Test', $names);
  }

  /**
   * Tests that the recipe finder follows symlinks.
   */
  public function testDiscoveryFollowsSymlinks(): void {
    $cookbook_dir = FileSystem::getOsTemporaryDirectory();
    $link_name = uniqid($cookbook_dir . '/recipe_link');
    $target = $this->root . '/core/recipes/administrator_role';

    $file_system = new SymfonyFilesystem();
    $file_system->symlink($target, $link_name);

    $recipes = iterator_to_array(new Finder($cookbook_dir));
    $this->assertCount(1, $recipes);
    $this->assertSame('Administrator role', reset($recipes)->name);

    $file_system->remove($link_name);
  }

}
