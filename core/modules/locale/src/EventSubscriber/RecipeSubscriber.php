<?php

namespace Drupal\locale\EventSubscriber;

use Drupal\Core\Recipe\RecipeAppliedEvent;
use Drupal\locale\LocaleConfigManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Updates config and translations when a recipe is applied.
 */
class RecipeSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected LocaleConfigManager $localeConfigManager,
  ) {}

  /**
   * Updates config and translations when a recipe is applied.
   *
   * @param \Drupal\Core\Recipe\RecipeAppliedEvent $event
   *   The recipe applied event.
   */
  public function onRecipeApply(RecipeAppliedEvent $event): void {
    $recipe_storage = $event->recipe->config->getConfigStorage();
    $this->localeConfigManager->updateDefaultConfigLangcodes($recipe_storage->listAll());
    // @todo don't update translations is a sub recipe or being processed in
    //   a batch.
    $this->localeConfigManager->updateConfigTranslationsFromStorage($recipe_storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [RecipeAppliedEvent::class => 'onRecipeApply'];
  }

}
