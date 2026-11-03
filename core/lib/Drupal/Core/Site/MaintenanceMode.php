<?php

namespace Drupal\Core\Site;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Provides the default implementation of the maintenance mode service.
 */
class MaintenanceMode implements MaintenanceModeInterface {

  public function __construct(
    protected readonly StateInterface $state,
    protected readonly ConfigFactoryInterface $configFactory,
    protected ?ThemeManagerInterface $themeManager,
    protected ?ThemeHandlerInterface $themeHandler,
    protected ?ThemeInitializationInterface $themeInitialization,
    protected ?ThemeExtensionList $themeExtensionList,
    /**
     * @var \Closure(): \Drupal\Core\Theme\Registry
     */
    #[AutowireServiceClosure(Registry::class)]
    protected readonly \Closure|ServiceClosureArgument $registryClosure,
    #[Autowire(param: 'app.root')]
    protected ?string $root,
  ) {
    if ($this->themeHandler === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $themeHandler argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/3058979', E_USER_DEPRECATED);
      $this->themeHandler = \Drupal::service('theme_handler');
    }
    if ($this->themeInitialization === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $themeInitialization argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/3058979', E_USER_DEPRECATED);
      $this->themeInitialization = \Drupal::service('theme.initialization');
    }
    if ($this->themeManager === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $themeManager argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/3058979', E_USER_DEPRECATED);
      $this->themeManager = \Drupal::service('theme.manager');
    }
    if ($this->themeExtensionList === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $themeExtensionList argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/3058979', E_USER_DEPRECATED);
      $this->themeExtensionList = \Drupal::service('extension.list.theme');
    }
    if (!$this->registryClosure instanceof \Closure) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $registryClosure argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/3058979', E_USER_DEPRECATED);
      $this->registryClosure = new ServiceClosureArgument(new Reference('theme.registry'));
    }
    if ($this->root === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $root argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/3058979', E_USER_DEPRECATED);
      $this->root = \Drupal::root();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    if (!$this->state->get('system.maintenance_mode')) {
      return FALSE;
    }

    if ($route = $route_match->getRouteObject()) {
      if ($route->getOption('_maintenance_access')) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function exempt(AccountInterface $account) {
    return $account->hasPermission('access site in maintenance mode');
  }

  /**
   * {@inheritdoc}
   */
  public function getSiteMaintenanceMessage() {
    return new FormattableMarkup($this->configFactory->get('system.maintenance')->get('message'), [
      '@site' => $this->configFactory->get('system.site')->get('name'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function setTheme(): void {
    // If the theme is already set, assume the others are set too, and do
    // nothing.
    if ($this->themeManager->hasActiveTheme()) {
      return;
    }

    require_once $this->root . '/theme.inc';
    require_once $this->root . '/common.inc';
    require_once $this->root . '/module.inc';

    // Install and update pages are treated differently to prevent theming
    // overrides.
    if (defined('MAINTENANCE_MODE') && (MAINTENANCE_MODE == 'install' || MAINTENANCE_MODE == 'update')) {
      if (InstallerKernel::installationAttempted()) {
        $customTheme = $GLOBALS['install_state']['theme'];
      }
      else {
        $customTheme = Settings::get('maintenance_theme', InstallerKernel::INSTALL_THEME);
      }
    }
    else {
      // Use the maintenance theme if specified, otherwise attempt to use the
      // default site theme.
      try {
        $customTheme = Settings::get('maintenance_theme', '');
        if (!$customTheme) {
          $customTheme = $this->configFactory->get('system.theme')->get('default');
        }
      }
      catch (\Exception) {
        // Whatever went wrong (often a database connection problem), we are
        // about to fall back to a sensible theme so there is no need for
        // special handling.
      }
      if (!$customTheme) {
        // We have been unable to identify the configured theme, so fall back
        // to a safe default. The admin theme is reasonably user friendly and
        // fairly generic.
        $customTheme = InstallerKernel::INSTALL_THEME;
      }
    }

    $themes = $this->themeHandler->listInfo();

    // If no themes are installed yet, or if the requested custom theme is not
    // installed, retrieve all available themes.
    if (empty($themes) || !isset($themes[$customTheme])) {
      $themes = $this->themeExtensionList->getList();
      $this->themeHandler->addTheme($themes[$customTheme]);
    }

    // \Drupal\Core\Extension\ThemeHandlerInterface::listInfo() triggers a
    // \Drupal\Core\Extension\ModuleHandler::alter() in maintenance mode, but
    // we can't let themes alter the .info.yml data until we know a theme's
    // base themes. So don't set active theme until after
    // \Drupal\Core\Extension\ThemeHandlerInterface::listInfo() builds its
    // cache.
    $theme = $customTheme;

    // Find all our ancestor themes and put them in an array.
    // @todo This is just a workaround. Find a better way how to handle themes
    //   on maintenance pages, see https://www.drupal.org/node/2322619.
    // This code is basically a duplicate of
    // \Drupal\Core\Theme\ThemeInitialization::getActiveThemeByName.
    $base_themes = [];
    $ancestor = $theme;
    while ($ancestor && isset($themes[$ancestor]->base_theme)) {
      $base_themes[] = $themes[$themes[$ancestor]->base_theme];
      $ancestor = $themes[$ancestor]->base_theme;
      if ($ancestor) {
        // Ensure that the base theme is added and installed.
        $this->themeHandler->addTheme($themes[$ancestor]);
      }
    }
    $this->themeManager->setActiveTheme($this->themeInitialization->getActiveTheme($themes[$customTheme], $base_themes));
    // Prime the theme registry.
    ($this->registryClosure)();
  }

}
