<?php

namespace Drupal\update\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Help hook implementations for the update module.
 */
class UpdateHelpHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.update':
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Update Status module periodically checks for new versions of your site\'s software (including contributed modules and themes), and alerts administrators to available updates. The Update Status system is also used by some other modules to manage updates and downloads; for example, the Interface Translation module uses the Update Status to download translations from the localization server. Note that whenever the Update Status system is used, anonymous usage statistics are sent to Drupal.org. If desired, you may uninstall the Update Status module from the <a href=":modules">Extend page</a>; if you do so, functionality that depends on the Update Status system will not work. For more information, see the <a href=":update">online documentation for the Update Status module</a>.', [
          ':update' => 'https://www.drupal.org/documentation/modules/update',
          ':modules' => Url::fromRoute('system.modules_list')->toString(),
        ]) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Checking for available updates') . '</dt>';
        $output .= '<dd>' . $this->t('The <a href=":update-report">Available updates report</a> displays core, contributed modules, and themes for which there are new releases available for download. On the report page, you can also check manually for updates. You can configure the frequency of update checks, which are performed during cron runs, and whether notifications are sent on the <a href=":update-settings">Update Status settings page</a>.', [
          ':update-report' => Url::fromRoute('update.status')->toString(),
          ':update-settings' => Url::fromRoute('update.settings')->toString(),
        ]) . '</dd>';
        $output .= '</dl>';
        return $output;

      case 'update.status':
        return '<p>' . $this->t('Here you can find information about available updates for your installed modules and themes. Note that each module or theme is part of a "project", which may or may not have the same name, and might include multiple modules or themes within it.') . '</p>';

      case 'system.modules_list':
        return '<p>' . $this->t('Regularly review <a href=":updates">available updates</a> and update as required to maintain a secure and current site. Always run the <a href=":update-php">update script</a> each time you update software.', [
          ':update-php' => Url::fromRoute('system.db_update')->toString(),
          ':updates' => Url::fromRoute('update.status')->toString(),
        ]) . '</p>';
    }
    return NULL;
  }

}
