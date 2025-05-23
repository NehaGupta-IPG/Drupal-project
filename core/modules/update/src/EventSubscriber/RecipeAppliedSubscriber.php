<?php

declare(strict_types=1);

namespace Drupal\update\EventSubscriber;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeAppliedEvent;
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
    if (!$this->needsTelemetry($event->recipe)) {
      return;
    }
    $recipe_queue = $this->queueFactory->get('update_recipe_telemetry', TRUE);
    $recipe_queue->createItem($this->getRecipeInfo($event->recipe));
  }

  /**
   * Decides if a given recipe needs telemetry or not.
   *
   * @param Drupal\Core\Recipe\Recipe $recipe
   *   The recipe that has been applied.
   *
   * @return bool
   *   TRUE if this recipe needs Drupal.org telemetry, otherwise FALSE.
   */
  protected function needsTelemetry(Recipe $recipe): bool {
    // We always want telemetry for Drupal Core recipes.
    if (str_starts_with($recipe->path, \Drupal::root() . '/core/recipes/')) {
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
    return [
      'machine_name' => basename($recipe->path),
      'human_name' => $recipe->name,
      'type' => $recipe->type,
      // @todo Get version info from Composer.
      'version' => 'TBD',
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
