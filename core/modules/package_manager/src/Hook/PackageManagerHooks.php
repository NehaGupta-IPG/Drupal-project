<?php

namespace Drupal\package_manager\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\package_manager\ComposerInspector;
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

        $output .= '<h3 id="package-manager-faq">' . $this->t('FAQ') . '</h3>';
        $output .= '<h4 id="package-manager-composer-related-faq">' . $this->t('FAQs related to Composer') . '</h4>';
        $output .= '<ul>';
        $output .= '  <li>' . $this->t('What if it says the <code>proc_open()</code> function is disabled on your PHP installation?');
        $output .= '    <p>' . $this->t('Ask your system administrator to remove <code>proc_open()</code> from the <a href=":url">disable_functions</a> setting in <code>php.ini</code>.', [':url' => 'https://www.php.net/manual/en/ini.core.php#ini.disable-functions']) . '</p>';
        $output .= '  </li>';
        $output .= '  <li>' . $this->t('What if it says the <code>composer</code> executable cannot be found?');
        $output .= '    <p>' . $this->t("If the <code>composer</code> executable's path cannot be automatically determined, it can be explicitly set by adding the following line to <code>settings.php</code>:") . '</p>';
        $output .= "    <pre><code>\$config['package_manager.settings']['executables']['composer'] = '/full/path/to/composer.phar';</code></pre>";
        $output .= '  </li>';
        $output .= '  <li>' . $this->t('What if it says the detected version of Composer is not supported?');
        $output .= '    <p>' . $this->t('The version of the <code>composer</code> executable must satisfy <code>@version</code>. See the <a href=":url">the Composer documentation</a> for more information, or use this command to update Composer:', [
          '@version' => ComposerInspector::SUPPORTED_VERSION,
          ':url' => 'https://getcomposer.org/doc/03-cli.md#self-update-selfupdate',
        ]) . '</p>';
        $output .= '    <pre><code>composer self-update</code></pre>';
        $output .= '  </li>';
        $output .= '  <li>' . $this->t('What if it says the <code>composer validate</code> command failed?');
        $output .= '    <p>' . $this->t('Composer detected problems with your <code>composer.json</code> and/or <code>composer.lock</code> files, and the project is not in a completely valid state. See <a href=":url">the Composer documentation</a> for more information.', [':url' => 'https://getcomposer.org/doc/04-schema.md']) . '</p>';
        $output .= '  </li>';
        $output .= '</ul>';
        $output .= '<h4 id="package-manager-faq-rsync">' . $this->t('Using rsync') . '</h4>';
        $output .= '<p>' . $this->t('Package Manager must be able to run <code>rsync</code> to copy files between the live site and the stage directory. Package Manager will try to detect the path to <code>rsync</code>, but if it cannot be detected, you can set it explicitly by adding the following line to <code>settings.php</code>:') . '</p>';
        $output .= "<pre><code>\$config['package_manager.settings']['executables']['rsync'] = '/full/path/to/rsync';</code></pre>";
        $output .= '<h4 id="package-manager-tuf-info">' . $this->t('Enabling PHP-TUF protection') . '</h4>';
        $output .= '<p>' . $this->t('Package Manager requires <a href=":php-tuf">PHP-TUF</a>, which implements <a href=":tuf">The Update Framework</a> as a way to help secure Composer package downloads via the <a href=":php-tuf-plugin">PHP-TUF Composer integration plugin</a>. This plugin must be installed and configured properly in order to use Package Manager.', [
          ':php-tuf' => 'https://github.com/php-tuf/php-tuf',
          ':tuf' => 'https://theupdateframework.io/',
          ':php-tuf-plugin' => 'https://github.com/php-tuf/composer-integration',
        ]) . '</p>';
        $output .= '<p>' . $this->t('To install and configure the plugin as needed, you can run the following commands:') . '</p>';
        $output .= '<pre><code>';
        $output .= "composer config allow-plugins.php-tuf/composer-integration true\n";
        $output .= "composer require php-tuf/composer-integration";
        $output .= '</code></pre>';
        $output .= '<p>' . $this->t('Package Manager currently requires the <code>https://packages.drupal.org/8</code> Composer repository to be protected by TUF. To set this up, run the following command:') . '</p>';
        $output .= '<pre><code>';
        $output .= "composer tuf:protect https://packages.drupal.org/8\n";
        $output .= '</code></pre>';
        $output .= '<h4 id="package-manager-faq-unsupported-composer-plugin">' . $this->t('What if it says I have unsupported Composer plugins in my codebase?') . '</h4>';
        $output .= '<p>' . $this->t('A fresh Drupal installation only uses supported Composer plugins, but some modules or themes may depend on additional Composer plugins. <a href=":new-issue">Create a new issue</a> when you encounter this.', [':new-issue' => 'https://www.drupal.org/node/add/project-issue/auto_updates']) . '</p>';
        $output .= '<p>' . $this->t('It is possible to <em>trust</em> additional Composer plugins, but this requires significant expertise: understanding the code of that Composer plugin, what the effects on the file system are and how it affects the Package Manager module. Some Composer plugins could result in a broken site!') . '</p>';
        $output .= '<h4 id="package-manager-faq-composer-patches-installed-or-removed">' . $this->t('What if it says <code>cweagans/composer-patches</code> cannot be installed/removed?') . '</h4>';
        $output .= '<p>' . $this->t('Installation or removal of <code>cweagans/composer-patches</code> via Package Manager is not supported. You can install or remove it manually by running Composer commands in your site root.') . '</p>';
        $output .= '<p>' . $this->t('To install it:') . '</p>';
        $output .= '<pre><code>composer require cweagans/composer-patches</code></pre>';
        $output .= '<p>' . $this->t('To remove it:') . '</p>';
        $output .= '<pre><code>composer remove cweagans/composer-patches</code></pre>';
        $output .= '<h4 id="package-manager-faq-composer-patches-not-a-root-dependency">' . $this->t('What if it says <code>cweagans/composer-patches</code> must be a root dependency?') . '</h4>';
        $output .= '<p>' . $this->t('If <code>cweagans/composer-patches</code> is installed, it must be defined as a dependency of the main project (i.e., it must be listed in the <code>require</code> or <code>require-dev</code> section of <code>composer.json</code>). You can run the following command in your site root to add it as a dependency of the main project:') . '</p>';
        $output .= "<pre><code>composer require cweagans/composer-patches</code></pre>";
        return $output;
    }
    return NULL;
  }

}
