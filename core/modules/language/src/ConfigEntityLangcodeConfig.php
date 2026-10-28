<?php

declare(strict_types=1);

namespace Drupal\language;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides config entity language selector visibility decisions.
 */
class ConfigEntityLangcodeConfig {

  /**
   * Constructs a new ConfigEntityLangcodeConfig.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Returns whether a config entity type should expose a language selector.
   *
   * @param string $entity_type_id
   *   The config entity type ID.
   *
   * @return bool
   *   TRUE when the language selector should be shown, FALSE otherwise.
   */
  public function showLangcode(string $entity_type_id): bool {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
    if (!$entity_type instanceof ConfigEntityTypeInterface || !$entity_type->isLangcodeConfigurable()) {
      return FALSE;
    }

    return (bool) $this->configFactory->get('language.config_type_settings')->get('show_langcode.' . $entity_type_id);
  }

}
