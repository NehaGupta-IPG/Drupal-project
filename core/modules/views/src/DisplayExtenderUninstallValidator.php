<?php

declare(strict_types=1);

namespace Drupal\views;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Prevents uninstallation of modules providing used views display extenders.
 */
class DisplayExtenderUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new FilterUninstallValidator.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $displayExtenderManager
   *   The display extender plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(protected PluginManagerInterface $displayExtenderManager, protected EntityTypeManagerInterface $entity_type_manager) {
  }

  /**
   * {@inheritdoc}
   */
  public function validate($module): array {
    $reasons = [];
    // Get display plugins supplied by this module.
    if ($this->getViewsDisplayExtenderByProvider($module) !== []) {
      $used_in = [];
      $views = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple();
      foreach ($views as $view) {
        /** @var \Drupal\views\Entity\View $view */
        $view_displays = $view->get('display');
        foreach ($view_displays as $display) {
          if (!\array_key_exists('display_options', $display)) {
            continue;
          }
          if ($display['display_options']['display_extenders'] === []) {
            continue;
          }
          $used_in[] = $view->id();
          break;
        }
      }

      if (!empty($used_in)) {
        $reasons[] = $this->t('Provides a display extender plugin that is in use in the following views: %views', ['%views' => implode(', ', $used_in)]);
      }
    }
    return $reasons;
  }

  /**
   * Returns all filter definitions that are provided by the specified provider.
   *
   * @param string $provider
   *   The provider of the filters.
   *
   * @return array
   *   The filter definitions for the specified provider.
   */
  protected function getViewsDisplayExtenderByProvider(string $provider): array {
    return array_filter($this->displayExtenderManager->getDefinitions(), function ($definition) use ($provider) {
      return $definition['provider'] === $provider;
    });
  }

}
