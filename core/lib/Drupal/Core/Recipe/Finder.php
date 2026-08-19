<?php

declare(strict_types=1);

namespace Drupal\Core\Recipe;

use Composer\InstalledVersions;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder as SymfonyFinder;

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
 * $discovery = new Finder('/path/to/recipes');
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
final class Finder implements \IteratorAggregate {

  /**
   * The location where Composer is configured to install recipes.
   */
  private static ?string $recipesDirectory = NULL;

  /**
   * A finder that scans for `recipe.yml` files.
   */
  public readonly SymfonyFinder $finder;

  public function __construct(
    string|array|null $directory = NULL,
    private readonly bool $skipInvalid = FALSE,
    public readonly ?LoggerInterface $logger = NULL,
  ) {
    $this->finder = SymfonyFinder::create()
      ->files()
      ->name('recipe.yml')
      ->depth(1)
      ->followLinks()
      ->in($directory ?? [
        // Placeholders used by the Composer installer don't make sense here.
        // For example, if Composer is configured to install recipes in
        // `./recipes/{$name}`, we really just want to scan `./recipes`.
        str_replace(['{$vendor}', '{$name}'], '', self::getComposerRecipesDirectory()),
        // Drupal runs in the web root, so this directory is guaranteed to exist
        // (and Finder will throw if it doesn't).
        'core/recipes',
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Traversable {
    foreach ($this->finder as $file) {
      try {
        yield Recipe::createFromDirectory($file->getPath());
      }
      catch (\Throwable $e) {
        $this->logger?->error($e->getMessage());
        if ($this->skipInvalid) {
          continue;
        }
        throw $e;
      }
    }
  }

  /**
   * Returns the directory where recipes are installed.
   *
   * Placeholder tokens, like {$name} and {$vendor}, are left as-is.
   *
   * @param string|null $project_root
   *   (optional) The root of the project, where the top-level `composer.json`
   *   is. Auto-detected by default.
   *
   * @return string
   *   The path where Composer has installed recipes.
   *
   * @throws \RuntimeException
   *   Thrown if the install directory for recipes cannot be determined.
   */
  public static function getComposerRecipesDirectory(?string $project_root = NULL): string {
    // This is expensive to compute, and very unlikely to change in a request.
    if (isset(self::$recipesDirectory)) {
      return self::$recipesDirectory;
    }

    if (empty($project_root)) {
      ['install_path' => $project_root] = InstalledVersions::getRootPackage();
      $project_root = realpath($project_root);
    }
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
