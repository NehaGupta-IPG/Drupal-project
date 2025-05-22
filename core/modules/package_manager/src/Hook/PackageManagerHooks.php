<?php

namespace Drupal\package_manager\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for package_manager.
 */
class PackageManagerHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name) : ?string {
    switch ($route_name) {
      case 'help.page.package_manager':
        $output = '<h3 id="package-manager-about">' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The Package Manager modules provides a framework for updating your site using <a href=":composer-introduction">Composer</a>.',
          [':composer-introduction' => 'https://getcomposer.org/doc/00-intro.md']) . '</p>';
        $output .= '<p>' . $this->t('For more information, see the <a href=":package_manager">online documentation for the Package Manager module</a>.', [':package_manager' => 'https://www.drupal.org/docs/develop/core-modules-and-themes/core-modules/package-manager-module']) . '</p>';

        $output .= '<h3 id="package-manager-limitations">' . $this->t('Limitations') . '</h3>';
        $output .= '<p>' . $this->t("Package Manager modifies the current site's code base. It is intentionally limited to prevent unexpected changes to the live site.") . '</p>';
        $output .= '<ul>';
        $output .= '  <li>' . $this->t('Drupal multi-site installations are not supported.') . '</li>';
        $output .= '  <li>' . $this->t('Only specifically allowed Composer plugins are supported. If you have any, see <a href="#package-manager-faq-unsupported-composer-plugin">What if it says I have unsupported Composer plugins in my codebase?</a>.') . '</li>';
        $output .= '  <li>' . $this->t('Version control operations are not supported. Site administrators are responsible for version control.') . '</li>';
        $output .= '  <li>' . $this->t('Only one copy of the site is maintained at any given time. If a copy of the site already exists, another one cannot be created until the existing copy is destroyed.') . '</li>';
        $output .= '  <li>' . $this->t('The temporary copy of the site is associated with the user or session that originally created it, and only that user or session can make changes to the copy.') . '</li>';
        $output .= '  <li>' . $this->t('Modules can not be uninstalled while syncing changes into live site.') . '</li>';
        $output .= '</ul>';

        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Setting the path to the Composer executable file') . '</dt>';
        $output .= '<dd>' . $this->t('Users who have the <em>Administer software updates</em> permission can set the path to the Composer executable from the <a href=":package-manager-settings">Package Manager settings page</a>.', [
          ':package-manager-settings' => Url::fromRoute('system.cron_settings')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Setting the path to the rsync executable file') . '</dt>';
        $output .= '<dd>' . $this->t('Users who have the <em>Administer software updates</em> permission can set the path to the rsync executable from the <a href=":package-manager-settings">Package Manager settings page</a>.', [
          ':package-manager-settings' => Url::fromRoute('system.cron_settings')->toString(),
        ]) . '</dd>';
        return $output;
    }
    return NULL;
  }

}
