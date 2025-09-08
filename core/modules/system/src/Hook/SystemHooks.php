<?php

namespace Drupal\system\Hook;

use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\Site\Settings;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for system.
 */
class SystemHooks {

  use StringTranslationTrait;

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
          ':drupal-modules' => 'https://www.drupal.org/project/project_module',
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Managing themes') . '</dt>';
        $output .= '<dd>' . $this->t('Users with appropriate permission can install and uninstall themes on the <a href=":themes">Appearance page</a>. Themes determine the design and presentation of your site. Depending on which distribution or installation profile you choose when you install your site, a default theme is installed, and possibly a different theme for administration pages. Other themes are provided but not installed, and additional contributed themes are available at the <a href=":drupal-themes">Drupal.org theme page</a>.', [
          ':themes' => Url::fromRoute('system.themes_page')->toString(),
          ':drupal-themes' => 'https://www.drupal.org/project/project_theme',
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
          ':page-cache' => \Drupal::moduleHandler()->moduleExists('page_cache') ? Url::fromRoute('help.page', [
            'name' => 'page_cache',
          ])->toString() : '#',
          ':dynamic-page-cache' => \Drupal::moduleHandler()->moduleExists('dynamic_page_cache') ? Url::fromRoute('help.page', [
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
        if (\Drupal::currentUser()->hasPermission('administer site configuration')) {
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
        $output = '<p>' . $this->t('Set and configure the default theme for your website.  Alternative <a href=":themes">themes</a> are available.', [':themes' => 'https://www.drupal.org/project/project_theme']) . '</p>';
        if (\Drupal::moduleHandler()->moduleExists('block')) {
          $output .= '<p>' . $this->t('You can place blocks for each theme on the <a href=":blocks">block layout</a> page.', [':blocks' => Url::fromRoute('block.admin_display')->toString()]) . '</p>';
        }
        return $output;

      case 'system.theme_settings_theme':
        $theme_list = \Drupal::service('theme_handler')->listInfo();
        $theme = $theme_list[$route_match->getParameter('theme')];
        return '<p>' . $this->t('These options control the display settings for the %name theme. When your site is displayed using this theme, these settings will be used.', ['%name' => $theme->info['name']]) . '</p>';

      case 'system.theme_settings':
        return '<p>' . $this->t('Control default display settings for your site, across all themes. Use theme-specific settings to override these defaults.') . '</p>';

      case 'system.modules_list':
        $output = '<p>' . $this->t('Add <a href=":modules">contributed modules</a> to extend your site\'s functionality.', [':modules' => 'https://www.drupal.org/project/project_module']) . '</p>';
        if (!\Drupal::moduleHandler()->moduleExists('update')) {
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
        if (\Drupal::currentUser()->id() == 1) {
          return '<p>' . $this->t('Use maintenance mode when making major updates, particularly if the updates could disrupt visitors or the update process. Examples include upgrading, importing or exporting content, modifying a theme, modifying content types, and making backups.') . '</p>';
        }
        break;

      case 'system.status':
        return '<p>' . $this->t("Here you can find a short overview of your site's parameters as well as any problems detected with your installation. It may be useful to copy and paste this information into support requests filed on Drupal.org's support forums and project issue queues. Before filing a support request, ensure that your web server meets the <a href=\":system-requirements\">system requirements.</a>", [':system-requirements' => 'https://www.drupal.org/docs/system-requirements']) . '</p>';
    }
    return NULL;
  }

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
   * Implements hook_filetransfer_info().
   */
  #[Hook('filetransfer_info')]
  public function filetransferInfo(): array {
    $backends = [];
    // This is the default, will be available on most systems.
    if (function_exists('ftp_connect')) {
      $backends['ftp'] = ['title' => $this->t('FTP'), 'class' => 'Drupal\Core\FileTransfer\FTP', 'weight' => 0];
    }
    // SSH2 lib connection is only available if the proper PHP extension is
    // installed.
    if (function_exists('ssh2_connect')) {
      $backends['ssh'] = ['title' => $this->t('SSH'), 'class' => 'Drupal\Core\FileTransfer\SSH', 'weight' => 20];
    }
    return $backends;
  }

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail($key, &$message, $params): void {
    $token_service = \Drupal::token();
    $context = $params['context'];
    $subject = PlainTextOutput::renderFromHtml($token_service->replace($context['subject'], $context));
    $body = $token_service->replace($context['message'], $context);
    $message['subject'] .= str_replace(["\r", "\n"], '', $subject);
    $message['body'][] = $body;
  }

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    $entity_types['date_format']->setFormClass('add', 'Drupal\system\Form\DateFormatAddForm')->setFormClass('edit', 'Drupal\system\Form\DateFormatEditForm')->setFormClass('delete', 'Drupal\system\Form\DateFormatDeleteForm')->setListBuilderClass('Drupal\system\DateFormatListBuilder')->setLinkTemplate('edit-form', '/admin/config/regional/date-time/formats/manage/{date_format}')->setLinkTemplate('delete-form', '/admin/config/regional/date-time/formats/manage/{date_format}/delete')->setLinkTemplate('collection', '/admin/config/regional/date-time/formats');
  }

  /**
   * Implements hook_block_view_BASE_BLOCK_ID_alter().
   */
  #[Hook('block_view_system_main_block_alter')]
  public function blockViewSystemMainBlockAlter(array &$build, BlockPluginInterface $block): void {
    // Contextual links on the system_main block would basically duplicate the
    // tabs/local tasks, so reduce the clutter.
    unset($build['#contextual_links']);
  }

  /**
   * Implements hook_query_TAG_alter() for entity reference selection handlers.
   */
  #[Hook('query_entity_reference_alter')]
  public function queryEntityReferenceAlter(AlterableInterface $query): void {
    $handler = $query->getMetadata('entity_reference_selection_handler');
    $handler->entityQueryAlter($query);
  }

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(&$type): void {
    if (isset($type['page'])) {
      $type['page']['#theme_wrappers']['off_canvas_page_wrapper'] = ['#weight' => -1000];
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
    $entity_definition_update_manager = \Drupal::entityDefinitionUpdateManager();
    foreach ($entity_definition_update_manager->getEntityTypes() as $entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface && $entity_type->hasRevisionMetadataKey('workspace')) {
        $entity_type->setRevisionMetadataKey('workspace', NULL);
        $entity_definition_update_manager->updateEntityType($entity_type);
      }
    }
  }

  /**
   * Implements hook_library_info_alter().
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(&$libraries, $extension): void {
    // If Claro is the admin theme but not the active theme, grant Claro the
    // ability to override the toolbar library with its own assets.
    if ($extension === 'toolbar' && _system_is_claro_admin_and_not_active()) {
      require_once DRUPAL_ROOT . '/core/themes/claro/claro.theme';
      claro_system_module_invoked_library_info_alter($libraries, $extension);
    }
  }

  /**
   * Implements hook_theme_registry_alter().
   */
  #[Hook('theme_registry_alter')]
  public function themeRegistryAlter(array &$theme_registry): void {
    // If Claro is the admin theme but not the active theme, use Claro's toolbar
    // templates.
    if (_system_is_claro_admin_and_not_active()) {
      require_once DRUPAL_ROOT . '/core/themes/claro/claro.theme';
      claro_system_module_invoked_theme_registry_alter($theme_registry);
    }
  }

  /**
   * Implements hook_page_top().
   */
  #[Hook('page_top')]
  public function pageTop(): void {
    /** @var \Drupal\Core\Routing\AdminContext $admin_context */
    $admin_context = \Drupal::service('router.admin_context');
    if ($admin_context->isAdminRoute() && \Drupal::currentUser()->hasPermission('administer site configuration')) {
      $route_match = \Drupal::routeMatch();
      $route_name = $route_match->getRouteName();
      if ($route_name !== 'system.status' && \Drupal::config('system.advisories')->get('enabled')) {
        /** @var \Drupal\system\SecurityAdvisories\SecurityAdvisoriesFetcher $fetcher */
        $fetcher = \Drupal::service('system.sa_fetcher');
        $advisories = $fetcher->getSecurityAdvisories(FALSE);
        if ($advisories) {
          $messenger = \Drupal::messenger();
          $display_as_errors = FALSE;
          $links = [];
          foreach ($advisories as $advisory) {
            // To ensure that all the advisory messages are grouped together on
            // the page, they must all be warnings or all be errors. If any
            // advisories are not public service announcements, then display all
            // the messages as errors because security advisories already tied
            // to a specific release are immediately actionable by upgrading to
            // a secure version of a project.
            $display_as_errors = $display_as_errors ? TRUE : !$advisory->isPsa();
            $links[] = new Link($advisory->getTitle(), Url::fromUri($advisory->getUrl()));
          }
          foreach ($links as $link) {
            $display_as_errors ? $messenger->addError($link) : $messenger->addWarning($link);
          }
          if (\Drupal::moduleHandler()->moduleExists('help')) {
            $help_link = $this->t('(<a href=":system-help">What are critical security announcements?</a>)', [
              ':system-help' => Url::fromRoute('help.page', [
                'name' => 'system',
              ], [
                'fragment' => 'security-advisories',
              ])->toString(),
            ]);
            $display_as_errors ? $messenger->addError($help_link) : $messenger->addWarning($help_link);
          }
        }
      }
    }
  }

  /**
   * Implements hook_file_download().
   */
  #[Hook('file_download')]
  public function fileDownload($uri): array|int|null {
    $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager');
    $scheme = $stream_wrapper_manager->getScheme($uri);
    if ($stream_wrapper_manager->isValidScheme($scheme)) {
      $target = $stream_wrapper_manager->getTarget($uri);
      if ($target !== FALSE) {
        if (!in_array($scheme, Settings::get('file_sa_core_2023_005_schemes', []))) {
          if (DIRECTORY_SEPARATOR !== '/') {
            $class = $stream_wrapper_manager->getClass($scheme);
            if (is_subclass_of($class, LocalStream::class)) {
              $target = str_replace(DIRECTORY_SEPARATOR, '/', $target);
            }
          }
          $parts = explode('/', $target);
          if (array_intersect($parts, ['.', '..'])) {
            return -1;
          }
        }
      }
    }
    $core_schemes = ['public', 'private', 'temporary'];
    $additional_public_schemes = array_diff(Settings::get('file_additional_public_schemes', []), $core_schemes);
    if ($additional_public_schemes) {
      $scheme = StreamWrapperManager::getScheme($uri);
      if (in_array($scheme, $additional_public_schemes, TRUE)) {
        return ['Cache-Control' => 'public'];
      }
    }
    return NULL;
  }

  /**
   * Implements hook_entity_form_mode_presave().
   *
   * Transforms empty description into null.
   */
  #[Hook('hook_entity_form_mode_presave')]
  public function systemEntityFormModePresave(EntityInterface $entity): void {
    if ($entity->get('description') !== NULL && trim($entity->get('description')) === '') {
      @trigger_error("Setting description to an empty string is deprecated in drupal:11.2.0 and it must be null in drupal:12.0.0. See https://www.drupal.org/node/3452144", E_USER_DEPRECATED);
      $entity->set('description', NULL);
    }
  }

}
