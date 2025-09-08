<?php

namespace Drupal\system\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Help hook implementations for system.
 */
class SystemHelpHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly AccountProxyInterface $currentUser,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly ThemeHandlerInterface $themeHandler,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.system':
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The System module is integral to the site: it provides user interfaces for many core systems and settings, as well as the basic administrative menu structure. For more information, see the <a href=":system">online documentation for the System module</a>.', [':system' => 'https://www.drupal.org/documentation/modules/system']) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Managing modules') . '</dt>';
        $output .= '<dd>' . $this->t('Users with appropriate permission can install and uninstall modules from the <a href=":modules">Extend page</a>. Depending on which distribution or installation profile you choose when you install your site, several modules are installed and others are provided but not installed. Each module provides a discrete set of features; modules may be installed or uninstalled depending on the needs of the site. Many additional modules contributed by members of the Drupal community are available for download from the <a href=":drupal-modules">Drupal.org module page</a>. Note that uninstalling a module is a destructive action: when you uninstall a module, you will permanently lose all data connected to the module.', [
          ':modules' => Url::fromRoute('system.modules_list')->toString(),
          ':drupal-modules' => 'https://www.drupal.org/project/modules',
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Managing themes') . '</dt>';
        $output .= '<dd>' . $this->t('Users with appropriate permission can install and uninstall themes on the <a href=":themes">Appearance page</a>. Themes determine the design and presentation of your site. Depending on which distribution or installation profile you choose when you install your site, a default theme is installed, and possibly a different theme for administration pages. Other themes are provided but not installed, and additional contributed themes are available at the <a href=":drupal-themes">Drupal.org theme page</a>.', [
          ':themes' => Url::fromRoute('system.themes_page')->toString(),
          ':drupal-themes' => 'https://www.drupal.org/project/themes',
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Disabling drag-and-drop functionality') . '</dt>';
        $output .= '<dd>' . $this->t('The default drag-and-drop user interface for ordering tables in the administrative interface presents a challenge for some users, including users of screen readers and other assistive technology. The drag-and-drop interface can be disabled in a table by clicking a link labeled "Show row weights" above the table. The replacement interface allows users to order the table by choosing numerical weights instead of dragging table rows.') . '</dd>';
        $output .= '<dt>' . $this->t('Configuring basic site settings') . '</dt>';
        $output .= '<dd>' . $this->t('The System module provides pages for managing basic site configuration, including <a href=":date-time-settings">Date and time formats</a> and <a href=":site-info">Basic site settings</a> (site name, email address to send mail from, home page, and error pages). Additional configuration pages are listed on the main <a href=":config">Configuration page</a>.', [
          ':date-time-settings' => Url::fromRoute('entity.date_format.collection')->toString(),
          ':site-info' => Url::fromRoute('system.site_information_settings')->toString(),
          ':config' => Url::fromRoute('system.admin_config')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Checking site status') . '</dt>';
        $output .= '<dd>' . $this->t('The <a href=":status">Status report</a> provides an overview of the configuration, status, and health of your site. Review this report to make sure there are not any problems to address, and to find information about the software your site and web server are using.', [':status' => Url::fromRoute('system.status')->toString()]) . '</dd>';
        $output .= '<dt>' . $this->t('Using maintenance mode') . '</dt>';
        $output .= '<dd>' . $this->t('When you are performing site maintenance, you can prevent non-administrative users (including anonymous visitors) from viewing your site by putting it in <a href=":maintenance-mode">Maintenance mode</a>. This will prevent unauthorized users from making changes to the site while you are performing maintenance, or from seeing a broken site while updates are in progress.', [
          ':maintenance-mode' => Url::fromRoute('system.site_maintenance_mode')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Configuring for performance') . '</dt>';
        $output .= '<dd>' . $this->t('On the <a href=":performance-page">Performance page</a>, the site can be configured to aggregate CSS and JavaScript files, making the total request size smaller. Note that, for small- to medium-sized websites, the <a href=":page-cache">Internal Page Cache module</a> should be installed so that pages are efficiently cached and reused for anonymous users. Finally, for websites of all sizes, the <a href=":dynamic-page-cache">Dynamic Page Cache module</a> should also be installed so that the non-personalized parts of pages are efficiently cached (for all users).', [
          ':performance-page' => Url::fromRoute('system.performance_settings')->toString(),
          ':page-cache' => $this->moduleHandler->moduleExists('page_cache') ? Url::fromRoute('help.page', [
            'name' => 'page_cache',
          ])->toString() : '#',
          ':dynamic-page-cache' => $this->moduleHandler->moduleExists('dynamic_page_cache') ? Url::fromRoute('help.page', [
            'name' => 'dynamic_page_cache',
          ])->toString() : '#',
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Configuring cron') . '</dt>';
        $output .= '<dd>' . $this->t('In order for the site and its modules to continue to operate well, a set of routine administrative operations must run on a regular basis; these operations are known as <em>cron</em> tasks. On the <a href=":cron">Cron page</a>, you can configure cron to run periodically as part of server responses by installing the <em>Automated Cron</em> module, or you can turn this off and trigger cron from an outside process on your web server. You can verify the status of cron tasks by visiting the <a href=":status">Status report page</a>. For more information, see the <a href=":handbook">online documentation for configuring cron jobs</a>.', [
          ':status' => Url::fromRoute('system.status')->toString(),
          ':handbook' => 'https://www.drupal.org/docs/administering-a-drupal-site/cron-automated-tasks/cron-automated-tasks-overview',
          ':cron' => Url::fromRoute('system.cron_settings')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Configuring the file system') . '</dt>';
        $output .= '<dd>' . $this->t('Your site has several file directories, which are used to store and process uploaded and generated files. The <em>public</em> file directory, which is configured in your settings.php file, is the default place for storing uploaded files. Links to files in this directory contain the direct file URL, so when the files are requested, the web server will send them directly without invoking your site code. This means that the files can be downloaded by anyone with the file URL, so requests are not access-controlled but they are efficient. The <em>private</em> file directory, also configured in your settings.php file and ideally located outside the site web root, is access controlled. Links to files in this directory are not direct, so requests to these files are mediated by your site code. This means that your site can check file access permission for each file before deciding to fulfill the request, so the requests are more secure, but less efficient. You should only use the private storage for files that need access control, not for files like your site logo and background images used on every page. The <em>temporary</em> file directory is used internally by your site code for various operations, and is configured on the <a href=":file-system">File system settings</a> page. You can also see the configured public and private file directories on this page, and choose whether public or private should be the default for uploaded files.', [
          ':file-system' => Url::fromRoute('system.file_system_settings')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Configuring the image toolkit') . '</dt>';
        $output .= '<dd>' . $this->t('On the <a href=":toolkit">Image toolkit page</a>, you can select and configure the PHP toolkit used to manipulate images. Depending on which distribution or installation profile you choose when you install your site, the GD2 toolkit and possibly others are included; other toolkits may be provided by contributed modules.', [
          ':toolkit' => Url::fromRoute('system.image_toolkit_settings')->toString(),
        ]) . '</dd>';
        if ($this->currentUser->hasPermission('administer site configuration')) {
          $output .= '<dt id="security-advisories">' . $this->t('Critical security advisories') . '</dt>';
          $output .= '<dd>' . $this->t('The System module displays highly critical and time-sensitive security announcements to site administrators. Some security announcements will be displayed until a critical security update is installed. Announcements that are not associated with a specific release will appear for a fixed period of time. <a href=":handbook">More information on critical security advisories</a>.', [
            ':handbook' => 'https://www.drupal.org/docs/updating-drupal/responding-to-critical-security-update-advisories',
          ]) . '</dd>';
          $output .= '<dd>' . $this->t('Only the most highly critical security announcements will be shown. <a href=":advisories-list">View all security announcements</a>.', [':advisories-list' => 'https://www.drupal.org/security']) . '</dd>';
        }
        $output .= '</dl>';
        return $output;

      case 'system.admin_index':
        return '<p>' . $this->t('This page shows you all available administration tasks for each module.') . '</p>';

      case 'system.themes_page':
        $output = '<p>' . $this->t('Set and configure the default theme for your website.  Alternative <a href=":themes">themes</a> are available.', [':themes' => 'https://www.drupal.org/project/themes']) . '</p>';
        if ($this->moduleHandler->moduleExists('block')) {
          $output .= '<p>' . $this->t('You can place blocks for each theme on the <a href=":blocks">block layout</a> page.', [':blocks' => Url::fromRoute('block.admin_display')->toString()]) . '</p>';
        }
        return $output;

      case 'system.theme_settings_theme':
        $theme_list = $this->themeHandler->listInfo();
        $theme = $theme_list[$route_match->getParameter('theme')];
        return '<p>' . $this->t('These options control the display settings for the %name theme. When your site is displayed using this theme, these settings will be used.', ['%name' => $theme->info['name']]) . '</p>';

      case 'system.theme_settings':
        return '<p>' . $this->t('Control default display settings for your site, across all themes. Use theme-specific settings to override these defaults.') . '</p>';

      case 'system.modules_list':
        $output = '<p>' . $this->t('Add <a href=":modules">contributed modules</a> to extend your site\'s functionality.', [':modules' => 'https://www.drupal.org/project/modules']) . '</p>';
        if (!$this->moduleHandler->moduleExists('update')) {
          $output .= '<p>' . $this->t('Regularly review available updates and update as required to maintain a secure and current site. Always run the <a href=":update-php">update script</a> each time a module is updated. Install the <a href=":update-status">Update Status module</a> to see a report of available releases for Drupal Core and contributed modules and themes.', [
            ':update-php' => Url::fromRoute('system.db_update')->toString(),
            ':update-status' => Url::fromRoute('system.modules_list', [], [
              'fragment' => 'module-update',
            ])->toString(),
          ]) . '</p>';
        }
        return $output;

      case 'system.modules_uninstall':
        return '<p>' . $this->t('The uninstall process removes all data related to a module.') . '</p>';

      case 'entity.block.edit_form':
        if (($block = $route_match->getParameter('block')) && $block->getPluginId() == 'system_powered_by_block') {
          return '<p>' . $this->t('The <em>Powered by Drupal</em> block is an optional link to the home page of the Drupal project. While there is absolutely no requirement that sites feature this link, it may be used to show support for Drupal.') . '</p>';
        }
        break;

      case 'block.admin_add':
        if ($route_match->getParameter('plugin_id') == 'system_powered_by_block') {
          return '<p>' . $this->t('The <em>Powered by Drupal</em> block is an optional link to the home page of the Drupal project. While there is absolutely no requirement that sites feature this link, it may be used to show support for Drupal.') . '</p>';
        }
        break;

      case 'system.site_maintenance_mode':
        if ($this->currentUser->id() == 1) {
          return '<p>' . $this->t('Use maintenance mode when making major updates, particularly if the updates could disrupt visitors or the update process. Examples include upgrading, importing or exporting content, modifying a theme, modifying content types, and making backups.') . '</p>';
        }
        break;

      case 'system.status':
        return '<p>' . $this->t("Here you can find a short overview of your site's parameters as well as any problems detected with your installation. It may be useful to copy and paste this information into support requests filed on Drupal.org's support forums and project issue queues. Before filing a support request, ensure that your web server meets the <a href=\":system-requirements\">system requirements.</a>", [':system-requirements' => 'https://www.drupal.org/docs/system-requirements']) . '</p>';
    }
    return NULL;
  }

}
