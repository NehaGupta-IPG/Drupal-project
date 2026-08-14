<?php

declare(strict_types=1);

namespace Drupal\Core\Recipe;

use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Instantiates and filters recipes discovered from the file system.
 *
 * Given an iterator that returns \Symfony\Component\Finder\SplFileInfo
 * instances, this converts those into Recipe objects and conditionally filters
 * out any invalid recipes.
 *
 * @see \Drupal\Core\Recipe\RecipeDiscovery
 * @see \Symfony\Component\Finder\SplFileInfo
 *
 * @internal
 *   This API is experimental.
 */
final class RecipeDiscoveryFilterIterator extends \FilterIterator {

  /**
   * Constructs a RecipeDiscoveryFilterIterator object.
   *
   * @param \Iterator $iterator
   *   An iterator returning \Symfony\Component\Finder\SplFileInfo instances.
   * @param bool $skipInvalid
   *   (optional) Whether recipes that have validation errors should be
   *   ignored.
   * @param \Psr\Log\LoggerInterface $logger
   *   (optional) A logger instance.
   */
  public function __construct(
    private readonly \Iterator $iterator,
    private readonly bool $skipInvalid = FALSE,
    private readonly ?LoggerInterface $logger = NULL,
  ) {
    parent::__construct($iterator);
  }

  /**
   * {@inheritdoc}
   */
  public function current(): mixed {
    /** @var \Symfony\Component\Finder\SplFileInfo $file */
    $file = parent::current();
    try {
      $current = Recipe::createFromDirectory($file->getPath());
      return $current;
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
        return NULL;
      }
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function accept(): bool {
    return $this->current() !== NULL;
  }

}
