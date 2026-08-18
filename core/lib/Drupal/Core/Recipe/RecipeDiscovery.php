<?php

declare(strict_types=1);

namespace Drupal\Core\Recipe;

use Composer\InstalledVersions;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

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
final class RecipeDiscovery {

  private static ?string $recipesDirectory = NULL;

  public static function create(?string $directory = NULL, bool $include_core = TRUE, bool $skip_invalid = TRUE, ?LoggerInterface $logger = NULL): Finder {
    $directories = [
      $directory ?? self::getComposerRecipesDirectory(),
    ];
    if ($include_core) {
      // Drupal boots in the web root, so this is guaranteed to be the location
      // of core recipes.
      $directories[] = 'core/recipes';
    }

    return Finder::create()
      ->files()
      ->name('recipe.yml')
      ->depth(1)
      ->followLinks()
      ->in($directories)
      ->filter(function (SplFileInfo $file) use ($skip_invalid, $logger): bool {
        try {
          Recipe::createFromDirectory($file->getPath());
        }
        catch (\Throwable $e) {
          // If we're ignoring invalid recipes, log the exception.
          if ($skip_invalid) {
            if ($logger) {
              Error::logException($logger, $e);
            }
            return FALSE;
          }
          throw $e;
        }
        return TRUE;
      });
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
  public static function getComposerRecipesDirectory(): ?string {
    // This is expensive to compute, and very unlikely to change in a request.
    if (isset(self::$recipesDirectory)) {
      return self::$recipesDirectory;
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
      return self::$recipesDirectory = $project_root . DIRECTORY_SEPARATOR . $directory;
    }
    throw new \RuntimeException("No recipe install location is configured in $file.");
  }

}
