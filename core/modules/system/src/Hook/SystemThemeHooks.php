<?php

namespace Drupal\system\Hook;

use Drupal\Core\Theme\ThemeCommonElements;
use Drupal\system\Theme\SystemAdminThemePreprocess;
use Drupal\Component\Gettext\PoItem;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\StackedRouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for system.
 */
class SystemThemeHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    $themeCommonElements = ThemeCommonElements::commonElements();

    $systemTheme = [
      // Normally theme suggestion templates are only picked up when they are in
      // themes. We explicitly define theme suggestions here so that the block
      // templates in core/modules/system/templates are picked up.
      'block__system_branding_block' => [
        'render element' => 'elements',
        'base hook' => 'block',
      ],
      'block__system_messages_block' => [
        'base hook' => 'block',
      ],
      'block__system_menu_block' => [
        'render element' => 'elements',
        'base hook' => 'block',
      ],
      'system_themes_page' => [
        'variables' => [
          'theme_groups' => [],
          'theme_group_titles' => [],
        ],
        'initial preprocess' => SystemAdminThemePreprocess::class . ':preprocessSystemThemesPage',
      ],
      'system_config_form' => [
        'render element' => 'form',
      ],
      'confirm_form' => [
        'render element' => 'form',
      ],
      'system_modules_details' => [
        'render element' => 'form',
        'initial preprocess' => SystemAdminThemePreprocess::class . ':preprocessSystemModulesDetails',
      ],
      'system_modules_uninstall' => [
        'render element' => 'form',
        'initial preprocess' => SystemAdminThemePreprocess::class . ':preprocessSystemModulesUninstall',
      ],
      'status_report_page' => [
        'variables' => [
          'counters' => [],
          'general_info' => [],
          'requirements' => NULL,
        ],
      ],
      'status_report' => [
        'variables' => [
          'grouped_requirements' => NULL,
          'requirements' => NULL,
        ],
      ],
      'status_report_counter' => [
        'variables' => [
          'amount' => NULL,
          'text' => NULL,
          'severity' => NULL,
        ],
      ],
      'status_report_general_info' => [
        'variables' => [
          'drupal' => [],
          'cron' => [],
          'database_system' => [],
          'database_system_version' => [],
          'php' => [],
          'php_memory_limit' => [],
          'webserver' => [],
        ],
      ],
      'admin_page' => [
        'variables' => [
          'blocks' => NULL,
        ],
        'initial preprocess' => SystemAdminThemePreprocess::class . ':preprocessAdminPage',
      ],
      'admin_block' => [
        'variables' => [
          'block' => NULL,
          'attributes' => [],
        ],
      ],
      'admin_block_content' => [
        'variables' => [
          'content' => NULL,
        ],
        'initial preprocess' => SystemAdminThemePreprocess::class . ':preprocessAdminBlockContent',
      ],
      'system_admin_index' => [
        'variables' => [
          'menu_items' => NULL,
        ],
        'initial preprocess' => SystemAdminThemePreprocess::class . ':preprocessSystemAdminIndex',
      ],
      'entity_add_list' => [
        'variables' => [
          'bundles' => [],
          'add_bundle_message' => NULL,
        ],
        'template' => 'entity-add-list',
        'initial preprocess' => static::class . ':preprocessEntityAddList',
      ],
      'system_security_advisories_fetch_error_message' => [
        'variables' => [
          'error_message' => [],
        ],
        'initial preprocess' => SystemAdminThemePreprocess::class . ':preprocessSystemSecurityAdvisoriesFetchErrorMessage',
      ],
      'entity_page_title' => [
        'variables' => [
          'attributes' => [],
          'title' => NULL,
          'entity' => NULL,
          'view_mode' => NULL,
        ],
      ],
    ];

    return array_merge($themeCommonElements, $systemTheme);
  }

  /**
   * Prepares variables for the list of available bundles.
   *
   * Default template: entity-add-list.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - bundles: An array of bundles with the label, description, add_link
   *     keys.
   *   - add_bundle_message: The message shown when there are no bundles. Only
   *     available if the entity type uses bundle entities.
   */
  public function preprocessEntityAddList(array &$variables): void {
    foreach ($variables['bundles'] as $bundle_name => $bundle_info) {
      $variables['bundles'][$bundle_name]['description'] = [
        '#markup' => $bundle_info['description'],
      ];
    }
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_html')]
  public function themeSuggestionsHtml(array $variables): array {
    $path_args = explode('/', trim(\Drupal::service('path.current')->getPath(), '/'));
    return theme_get_suggestions($path_args, 'html');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_page')]
  public function themeSuggestionsPage(array $variables): array {
    $path_args = explode('/', trim(\Drupal::service('path.current')->getPath(), '/'));
    $suggestions = theme_get_suggestions($path_args, 'page');
    $supported_http_error_codes = [
      401,
      403,
      404,
    ];
    $exception = \Drupal::requestStack()->getCurrentRequest()->attributes->get('exception');
    if ($exception instanceof HttpExceptionInterface && in_array($exception->getStatusCode(), $supported_http_error_codes, TRUE)) {
      $suggestions[] = 'page__4xx';
      $suggestions[] = 'page__' . $exception->getStatusCode();
    }
    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_maintenance_page')]
  public function themeSuggestionsMaintenancePage(array $variables): array {
    $suggestions = [];
    // Dead databases will show error messages so supplying this template will
    // allow themers to override the page and the content completely.
    $offline = defined('MAINTENANCE_MODE');
    try {
      \Drupal::service('path.matcher')->isFrontPage();
    }
    catch (\Exception) {
      // The database is not yet available.
      $offline = TRUE;
    }
    if ($offline) {
      $suggestions[] = 'maintenance_page__offline';
    }
    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_region')]
  public function themeSuggestionsRegion(array $variables): array {
    $suggestions = [];
    if (!empty($variables['elements']['#region'])) {
      $suggestions[] = 'region__' . $variables['elements']['#region'];
    }
    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_field')]
  public function themeSuggestionsField(array $variables): array {
    $suggestions = [];
    $element = $variables['element'];
    $suggestions[] = 'field__' . $element['#field_type'];
    $suggestions[] = 'field__' . $element['#field_name'];
    $suggestions[] = 'field__' . $element['#entity_type'] . '__' . $element['#bundle'];
    $suggestions[] = 'field__' . $element['#entity_type'] . '__' . $element['#field_name'];
    $suggestions[] = 'field__' . $element['#entity_type'] . '__' . $element['#field_name'] . '__' . $element['#bundle'];
    return $suggestions;
  }

  /**
   * Implements hook_preprocess_HOOK() for block templates.
   */
  #[Hook('preprocess_block')]
  public function preprocessBlock(&$variables): void {
    switch ($variables['base_plugin_id']) {
      case 'system_branding_block':
        $variables['site_logo'] = '';
        if ($variables['content']['site_logo']['#access'] && $variables['content']['site_logo']['#uri']) {
          $variables['site_logo'] = $variables['content']['site_logo']['#uri'];
        }
        $variables['site_name'] = '';
        if ($variables['content']['site_name']['#access'] && $variables['content']['site_name']['#markup']) {
          $variables['site_name'] = $variables['content']['site_name']['#markup'];
        }
        $variables['site_slogan'] = '';
        if ($variables['content']['site_slogan']['#access'] && $variables['content']['site_slogan']['#markup']) {
          $variables['site_slogan'] = [
            '#markup' => $variables['content']['site_slogan']['#markup'],
          ];
        }
        break;
    }
  }

  /**
   * Implements hook_preprocess_toolbar().
   */
  #[Hook('preprocess_toolbar')]
  public function preprocessToolbar(array &$variables, $hook, $info): void {
    // When Claro is the admin theme, Claro overrides the active theme's if that
    // active theme is not Claro. Because of these potential overrides, the
    // toolbar cache should be invalidated any time the default or admin theme
    // changes.
    $variables['#cache']['tags'][] = 'config:system.theme';
    // If Claro is the admin theme but not the active theme, still include
    // Claro's toolbar preprocessing.
    if (_system_is_claro_admin_and_not_active()) {
      require_once DRUPAL_ROOT . '/core/themes/claro/claro.theme';
      claro_preprocess_toolbar($variables, $hook, $info);
    }
  }

  /**
   * Implements hook_js_settings_build().
   *
   * Sets values for the core/drupal.ajax library, which just depends on the
   * active theme but no other request-dependent values.
   */
  #[Hook('js_settings_build')]
  public function jsSettingsBuild(&$settings, AttachedAssetsInterface $assets): void {
    // Generate the values for the core/drupal.ajax library.
    // We need to send ajaxPageState settings for core/drupal.ajax if:
    // - ajaxPageState is being loaded in this Response, in which case it will
    //   already exist at $settings['ajaxPageState'] (because the
    //   core/drupal.ajax library definition specifies a placeholder
    //   'ajaxPageState' setting).
    // - core/drupal.ajax already has been loaded and hence this is an AJAX
    //   Response in which we must send the list of extra asset libraries that
    //   are being added in this AJAX Response.
    /** @var \Drupal\Core\Asset\LibraryDependencyResolver $library_dependency_resolver */
    $library_dependency_resolver = \Drupal::service('library.dependency_resolver');
    if (isset($settings['ajaxPageState']) || in_array('core/drupal.ajax', $library_dependency_resolver->getLibrariesWithDependencies($assets->getAlreadyLoadedLibraries()))) {
      // Provide the page with information about the theme that's used, so that
      // a later AJAX request can be rendered using the same theme.
      // @see \Drupal\Core\Theme\AjaxBasePageNegotiator
      $theme_key = \Drupal::theme()->getActiveTheme()->getName();
      $settings['ajaxPageState']['theme'] = $theme_key;
    }
  }

  /**
   * Implements hook_js_settings_alter().
   *
   * Sets values which depend on the current request, like core/drupalSettings
   * as well as theme_token ajax state.
   */
  #[Hook('js_settings_alter')]
  public function jsSettingsAlter(&$settings, AttachedAssetsInterface $assets): void {
    // As this is being output in the final response always use the main
    // request.
    $request = \Drupal::requestStack()->getMainRequest();
    $current_query = $request->query->all();
    // Let output path processors set a prefix.
    /** @var \Drupal\Core\PathProcessor\OutboundPathProcessorInterface $path_processor */
    $path_processor = \Drupal::service('path_processor_manager');
    $options = ['prefix' => ''];
    $path_processor->processOutbound('/', $options);
    $pathPrefix = $options['prefix'];
    $route_match = \Drupal::routeMatch();
    if ($route_match instanceof StackedRouteMatchInterface) {
      $route_match = $route_match->getMasterRouteMatch();
    }
    $current_path = $route_match->getRouteName() ? Url::fromRouteMatch($route_match)->getInternalPath() : '';
    $current_path_is_admin = \Drupal::service('router.admin_context')->isAdminRoute($route_match->getRouteObject());
    $path_settings = [
      'baseUrl' => $request->getBaseUrl() . '/',
      'pathPrefix' => $pathPrefix,
      'currentPath' => $current_path,
      'currentPathIsAdmin' => $current_path_is_admin,
      'isFront' => \Drupal::service('path.matcher')->isFrontPage(),
      'currentLanguage' => \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId(),
    ];
    if (!empty($current_query)) {
      ksort($current_query);
      $path_settings['currentQuery'] = (object) $current_query;
    }
    // Only set core/drupalSettings values that haven't been set already.
    foreach ($path_settings as $key => $value) {
      if (!isset($settings['path'][$key])) {
        $settings['path'][$key] = $value;
      }
    }
    if (!isset($settings['pluralDelimiter'])) {
      $settings['pluralDelimiter'] = PoItem::DELIMITER;
    }
    // Add the theme token to ajaxPageState, ensuring the database is available
    // before doing so. Also add the loaded libraries to ajaxPageState.
    /** @var \Drupal\Core\Asset\LibraryDependencyResolver $library_dependency_resolver */
    $library_dependency_resolver = \Drupal::service('library.dependency_resolver');
    $loaded_libraries = [];
    if (!isset($settings['ajaxPageState'])) {
      $loaded_libraries = $library_dependency_resolver->getLibrariesWithDependencies($assets->getAlreadyLoadedLibraries());
    }
    if (isset($settings['ajaxPageState']) || in_array('core/drupal.ajax', $loaded_libraries) || in_array('core/drupal.htmx', $loaded_libraries)) {
      if (!defined('MAINTENANCE_MODE')) {
        // The theme token is only validated when the theme requested is not the
        // default, so don't generate it unless necessary.
        // @see \Drupal\Core\Theme\AjaxBasePageNegotiator::determineActiveTheme()
        $active_theme_key = \Drupal::theme()->getActiveTheme()->getName();
        if ($active_theme_key !== \Drupal::service('theme_handler')->getDefault()) {
          $settings['ajaxPageState']['theme_token'] = \Drupal::csrfToken()->get($active_theme_key);
        }
      }
      // Provide the page with information about the individual asset libraries
      // used, information not otherwise available when aggregation is enabled.
      $minimal_libraries = $library_dependency_resolver->getMinimalRepresentativeSubset(array_unique(array_merge($assets->getLibraries(), $assets->getAlreadyLoadedLibraries())));
      sort($minimal_libraries);
      $settings['ajaxPageState']['libraries'] = implode(',', $minimal_libraries);
    }
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

}
