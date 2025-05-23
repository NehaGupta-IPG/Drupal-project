<?php

declare(strict_types=1);

namespace Drupal\update\EventSubscriber;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeAppliedEvent;
use Drupal\update\ComposerHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides telemetry about applied recipes by phoning home to Drupal.org.
 */
class RecipeAppliedSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a RecipeAppliedSubscriber object.
   */
  public function __construct(
    protected readonly QueueFactory $queueFactory,
  ) {}

  /**
   * Notice when a specific recipe is applied and add to a queue for processing.
   */
  public function onRecipeApplied(RecipeAppliedEvent $event): void {
    $recipe_info = $this->getRecipeInfo($event->recipe);
    if (!$this->needsTelemetry($recipe_info)) {
      return;
    }
    $recipe_queue = $this->queueFactory->get('update_recipe_telemetry', TRUE);
    $recipe_queue->createItem($recipe_info);
  }

  /**
   * Decides if a given recipe needs telemetry or not.
   *
   * @param string[] $recipe_info
   *   Information about the recipe that has been applied, as returned by
   *   self::getRecipeInfo().
   *
   * @return bool
   *   TRUE if this recipe needs Drupal.org telemetry, otherwise FALSE.
   *
   * @see self::getRecipeInfo()
   */
  protected function needsTelemetry(array $recipe_info): bool {
    // We always want telemetry for Drupal Core recipes.
    if (str_starts_with($recipe_info['path'], \Drupal::root() . '/core/recipes/')) {
      return TRUE;
    }
    // @todo Add logic to only report Drupal.org contributed recipes.
    return TRUE;
  }

  /**
   * Gets info about a specific recipe to add to the telemetry queue.
   *
   * @param Drupal\Core\Recipe\Recipe $recipe
   *   The recipe that has been applied.
   *
   * @return string[]
   *   Associative array of information about the applied recipe.
   */
  protected function getRecipeInfo(Recipe $recipe): array {
    $composer_name = ComposerHelper::getPackageName($recipe->path . '/composer.json');
    $composer_version = $composer_name ? ComposerHelper::getInstalledVersion($composer_name) : NULL;
    return [
      'path' => $recipe->path,
      'machine_name' => basename($recipe->path),
      'human_name' => $recipe->name,
      'type' => $recipe->type,
      'composer_name' => $composer_name,
      'composer_version' => $composer_version,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RecipeAppliedEvent::class => ['onRecipeApplied'],
    ];
  }

}
