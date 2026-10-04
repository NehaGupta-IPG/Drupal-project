<?php

declare(strict_types=1);

namespace Drupal\comment_deprecation_test\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;

/**
 * Hook implementations for comment_deprecation_test module.
 */
final readonly class CommentDeprecationTestHooks {

  public function __construct(
    protected ModuleExtensionList $moduleList,
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_theme_registry_alter().
   */
  #[Hook('theme_registry_alter')]
  public function alterThemeRegistry(array &$registry): void {
    if ($this->state->get('comment_deprecation_test.use_deprecated', FALSE)) {
      $registry['field__comment']['path'] = $this->moduleList->getPath('comment_deprecation_test') . '/templates';
    }
  }

}
