<?php

declare(strict_types=1);

namespace Drupal\system\Hook;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Module hook implementations for system.
 */
class SystemModuleHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager,
  ) {}

  /**
   * Implements hook_updater_info().
   */
  #[Hook('updater_info')]
  public function updaterInfo(): array {
    return [
      'module' => [
        'class' => 'Drupal\Core\Updater\Module',
        'name' => $this->t('Update modules'),
        'weight' => 0,
      ],
      'theme' => [
        'class' => 'Drupal\Core\Updater\Theme',
        'name' => $this->t('Update themes'),
        'weight' => 0,
      ],
    ];
  }

  /**
   * Implements hook_system_info_alter().
   */
  #[Hook('system_info_alter')]
  public function systemInfoAlter(&$info, Extension $file, $type): void {
    // Remove page-top and page-bottom from the blocks UI since they are
    // reserved for modules to populate from outside the blocks system.
    if ($type == 'theme') {
      $info['regions_hidden'][] = 'page_top';
      $info['regions_hidden'][] = 'page_bottom';
    }
  }

  /**
   * Implements hook_modules_uninstalled().
   */
  #[Hook('modules_uninstalled')]
  public function modulesUninstalled($modules): void {
    // @todo Remove this when modules are able to maintain their revision metadata
    //   keys.
    //   @see https://www.drupal.org/project/drupal/issues/3074333
    if (!in_array('workspaces', $modules, TRUE)) {
      return;
    }
    foreach ($this->entityDefinitionUpdateManager->getEntityTypes() as $entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface && $entity_type->hasRevisionMetadataKey('workspace')) {
        $entity_type->setRevisionMetadataKey('workspace', NULL);
        $this->entityDefinitionUpdateManager->updateEntityType($entity_type);
      }
    }
  }

}
