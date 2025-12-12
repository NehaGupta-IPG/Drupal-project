<?php

declare(strict_types=1);

namespace Drupal\CustomInstaller;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hooks which are registered and executed during the installer.
 *
 * @todo Also implement hook_install_tasks() and hook_install_tasks_alter().
 */
final class Hooks {

  #[Hook('form_install_select_language_form_alter')]
  public function alterSelectLanguageForm(array &$form): void {
    $form['alteration']['#markup'] = 'This was added by a hook in the early installer.';
  }

  #[Hook('form_install_configure_form_alter')]
  public function alterSiteConfigureForm(array &$form): void {
    $form['alteration']['#markup'] = 'This was added by a hook in the late installer.';
  }

}
