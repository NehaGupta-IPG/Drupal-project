<?php

declare(strict_types=1);

namespace Drupal\Core\Recipe;

use Composer\InstalledVersions;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Discovers recipes at specific locations in the file system.
 *
 * This scans a single directory of your choosing, as well as core's recipes.
 * By design, it won't search anywhere else in the file system, which is an
 * intentional difference from ExtensionDiscovery, because all recipes (except
 * core's) must be installed in a single location.
 *
 * Once instantiated, this object should be used as a simple iterator over
 * \Drupal\Core\Recipe\Recipe objects. For example:
 *
 * @code
 * $discovery = new RecipeDiscovery('/path/to/recipes');
 * foreach ($discovery as $recipe) {
 *   echo $recipe->name;
 * }
 * @endcode
 *
 * @see \Drupal\Core\Recipe\RecipeConfigurator
 *
 * @internal
 *   This API is experimental.
 */
final class RecipeDiscovery implements \IteratorAggregate, LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * The directories to search for recipes.
   *
   * @var string[]
   */
  private array $directoriesToSearch = [];

  /**
   * The directory for core recipes.
   */
  protected const string CORE_RECIPES_DIRECTORY = 'core/recipes';

  /**
   * Constructs a recipe discovery object.
   *
   * @param string|null $recipesDirectory
   *   (optional) A path containing directories that contain a recipe.yml file.
   *   There will be no further traversal into the directory tree.
   * @param bool $includeCoreRecipes
   *   (optional) Whether to include recipes provided by core.
   * @param bool $skipInvalid
   *   (optional) Whether recipes that have validation errors should be ignored.
   * @param array|null $pathsToExclude
   *   (optional) Paths to exclude from searching for recipes. Can be used to
   *   exclude recipes such as the example recipe included in Drupal core.
   */
  public function __construct(
    private readonly ?string $recipesDirectory = NULL,
    private readonly bool $includeCoreRecipes = TRUE,
    private readonly bool $skipInvalid = FALSE,
    private readonly ?array $pathsToExclude = NULL,
  ) {
    if ($includeCoreRecipes) {
      $this->directoriesToSearch[] = \Drupal::root() . DIRECTORY_SEPARATOR . self::CORE_RECIPES_DIRECTORY;
    }

    // If there are no Composer-installed recipes, we will only find core
    // recipes.
    $recipesDirectory ??= self::getComposerRecipesDirectory();
    if ($recipesDirectory) {
      assert(is_dir($recipesDirectory));
      $this->directoriesToSearch[] = $recipesDirectory;
    }
  }

  /**
   * Returns the directory where recipes are installed.
   *
   * Placeholder tokens, like {$name} and {$vendor}, are left as-is.
   *
   * @return string|null
   *   The path where Composer has installed recipes, or NULL if no recipes
   *   are installed.
   */
  private static function getComposerRecipesDirectory(): ?string {
    // This is expensive to compute, and very unlikely to change in a request.
    static $recipesDirectory;
    if (isset($recipesDirectory)) {
      return $recipesDirectory;
    }

    ['install_path' => $project_root] = InstalledVersions::getRootPackage();
    $project_root = realpath($project_root);
    assert(is_string($project_root));

    $file = $project_root . DIRECTORY_SEPARATOR . 'composer.json';
    $data = (string) file_get_contents($file);
    $data = json_decode($data, TRUE, flags: JSON_THROW_ON_ERROR);

    $directory = array_find_key(
      $data['extra']['installer-paths'] ?? [],
      fn (array $criteria): bool => in_array('type:' . Recipe::COMPOSER_PROJECT_TYPE, $criteria, TRUE),
    );
    if ($directory) {
      $recipesDirectory = $project_root . DIRECTORY_SEPARATOR . $directory;
    }
    return $recipesDirectory;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Traversable {
    $finder = Finder::create()
      ->files()
      ->name('recipe.yml')
      ->depth(1)
      ->followLinks()
      ->in($this->directoriesToSearch);
    if ($this->pathsToExclude) {
      $finder->notPath($this->pathsToExclude);
    }
    /** @var \Symfony\Component\Finder\SplFileInfo $file */
    foreach ($finder as $file) {
      try {
        yield Recipe::createFromDirectory($file->getPath());
      }
      catch (RecipeFileException |
        RecipeMissingExtensionsException |
        RecipePreExistingConfigException |
        \RuntimeException |
        UnknownRecipeException |
        ValidationFailedException $e) {
        // If we're ignoring invalid recipes, log the exception message (if a
        // logger has been set).
        if ($this->skipInvalid) {
          $this->logger?->warning($e->getMessage());
          continue;
        }
        throw $e;
      }
    }
  }

}
