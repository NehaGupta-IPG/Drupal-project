<?php

namespace Drupal\system\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\PreExistingConfigException;
use Drupal\Core\Config\UnmetDependenciesException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\system\Form\ExtensionFormTrait;
use Drupal\system\Form\ExtensionConfirmForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for theme handling.
 */
class ThemeController extends ControllerBase {

  use ExtensionFormTrait;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * An extension discovery instance.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList
   */
  protected $themeList;

  /**
   * The theme installer service.
   *
   * @var \Drupal\Core\Extension\ThemeInstallerInterface
   */
  protected $themeInstaller;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The module installer.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Constructs a new ThemeController.
   *
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_list
   *   The theme extension list.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ThemeInstallerInterface $theme_installer
   *   The theme installer.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   The module installer.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   */
  public function __construct(ThemeHandlerInterface $theme_handler, ThemeExtensionList $theme_list, ConfigFactoryInterface $config_factory, ThemeInstallerInterface $theme_installer, ?ModuleHandlerInterface $module_handler = NULL, ?ModuleInstallerInterface $module_installer = NULL, ?ModuleExtensionList $module_extension_list = NULL) {
    $this->themeHandler = $theme_handler;
    $this->themeList = $theme_list;
    $this->configFactory = $config_factory;
    $this->themeInstaller = $theme_installer;
    if ($module_handler === NULL) {
      @trigger_error('Calling ' . __NAMESPACE__ . '\SystemController::__construct without the module_handler argument is deprecated in drupal:11.2.0 and it will be required in drupal:12.0.0. See https://www.drupal.org/node/3188195', E_USER_DEPRECATED);
      $module_handler = \Drupal::service('module_handler');
    }
    $this->moduleHandler = $module_handler;
    if ($module_installer === NULL) {
      @trigger_error('Calling ' . __NAMESPACE__ . '\SystemController::__construct without the module_installer argument is deprecated in drupal:11.2.0 and it will be required in drupal:12.0.0. See https://www.drupal.org/node/3188195', E_USER_DEPRECATED);
      $module_installer = \Drupal::service('module_installer');
    }
    $this->moduleInstaller = $module_installer;
    if ($module_extension_list === NULL) {
      @trigger_error('Calling ' . __NAMESPACE__ . '\SystemController::__construct without the extension.list.module argument is deprecated in drupal:11.2.0 and it will be required in drupal:12.0.0. See https://www.drupal.org/node/3188195', E_USER_DEPRECATED);
      $module_extension_list = \Drupal::service('extension.list.module');
    }
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * Uninstalls a theme.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object containing a theme name and a valid token.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects back to the appearance admin page.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws access denied when no theme or token is set in the request or when
   *   the token is invalid.
   */
  public function uninstall(Request $request) {
    $theme = $request->query->get('theme');
    $config = $this->config('system.theme');

    if (isset($theme)) {
      // Get current list of themes.
      $themes = $this->themeHandler->listInfo();

      // Check if the specified theme is one recognized by the system.
      if (!empty($themes[$theme])) {
        // Do not uninstall the default or admin theme.
        if ($theme === $config->get('default') || $theme === $config->get('admin')) {
          $this->messenger()->addError($this->t('%theme is the default theme and cannot be uninstalled.', ['%theme' => $themes[$theme]->info['name']]));
        }
        else {
          $this->themeInstaller->uninstall([$theme]);
          $this->messenger()->addStatus($this->t('The %theme theme has been uninstalled.', ['%theme' => $themes[$theme]->info['name']]));
        }
      }
      else {
        $this->messenger()->addError($this->t('The %theme theme was not found.', ['%theme' => $theme]));
      }

      return $this->redirect('system.themes_page');
    }

    throw new AccessDeniedHttpException();
  }

  /**
   * Installs a theme.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object containing a theme name and a valid token.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   Redirects back to the appearance admin page or the confirmation form
   *   if an experimental theme will be installed.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws access denied when no theme or token is set in the request or when
   *   the token is invalid.
   */
  public function install(Request $request) {
    $theme = $request->query->get('theme');

    if (isset($theme)) {
      if ($confirmation_form = $this->checkConfirmationFormAndModules($request, $theme)) {
        return $confirmation_form;
      }

      try {
        if ($this->themeInstaller->install([$theme])) {
          $themes = $this->themeHandler->listInfo();
          $this->messenger()->addStatus($this->t('The %theme theme has been installed.', ['%theme' => $themes[$theme]->info['name']]));
        }
        else {
          $this->messenger()->addError($this->t('The %theme theme was not found.', ['%theme' => $theme]));
        }
      }
      catch (PreExistingConfigException $e) {
        $config_objects = $e->flattenConfigObjects($e->getConfigObjects());
        $this->messenger()->addError(
          $this->formatPlural(
            count($config_objects),
            'Unable to install @extension, %config_names already exists in active configuration.',
            'Unable to install @extension, %config_names already exist in active configuration.',
            [
              '%config_names' => implode(', ', $config_objects),
              '@extension' => $theme,
            ])
        );
      }
      catch (UnmetDependenciesException $e) {
        $this->messenger()->addError($e->getTranslatedMessage($this->getStringTranslation(), $theme));
      }
      catch (MissingDependencyException) {
        $this->messenger()->addError($this->t('Unable to install @theme due to missing module dependencies.', ['@theme' => $theme]));
      }

      return $this->redirect('system.themes_page');
    }

    throw new AccessDeniedHttpException();
  }

  /**
   * Checks if the given theme requires the installation of experimental themes.
   *
   * @param string $theme
   *   The name of the theme to check.
   *
   * @return bool
   *   Whether experimental themes will be installed.
   */
  protected function willInstallExperimentalTheme($theme) {
    $all_themes = $this->themeList->getList();
    $dependencies = array_keys($all_themes[$theme]->requires);
    $themes_to_enable = array_merge([$theme], $dependencies);

    foreach ($themes_to_enable as $name) {
      if (isset($all_themes[$name]) && $all_themes[$name]->isExperimental() && $all_themes[$name]->status === 0) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Set the default theme.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object containing a theme name.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   Redirects back to the appearance admin page or the confirmation form
   *   if an experimental theme will be installed.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws access denied when no theme is set in the request.
   */
  public function setDefaultTheme(Request $request) {
    $config = $this->configFactory->getEditable('system.theme');
    $theme = $request->query->get('theme');

    if (isset($theme)) {
      if ($confirmation_form = $this->checkConfirmationFormAndModules($request, $theme, TRUE)) {
        return $confirmation_form;
      }

      // Check if the specified theme is one recognized by the system.
      // Or try to install the theme.
      if ($this->themeInstaller->install([$theme])) {
        $themes = $this->themeHandler->listInfo();

        // Set the default theme.
        $config->set('default', $theme)->save();

        // The status message depends on whether an admin theme is currently in
        // use: a value of 0 means the admin theme is set to be the default
        // theme.
        $admin_theme = $config->get('admin');
        if (!empty($admin_theme) && $admin_theme != $theme) {
          $this->messenger()
            ->addStatus($this->t('Note that the administration theme is still set to the %admin_theme theme; consequently, the theme on this page remains unchanged. All non-administrative sections of the site, however, will show the selected %selected_theme theme by default.', [
              '%admin_theme' => $themes[$admin_theme]->info['name'],
              '%selected_theme' => $themes[$theme]->info['name'],
            ]));
        }
        else {
          $this->messenger()->addStatus($this->t('%theme is now the default theme.', ['%theme' => $themes[$theme]->info['name']]));
        }
      }
      else {
        $this->messenger()->addError($this->t('The %theme theme was not found.', ['%theme' => $theme]));
      }

      return $this->redirect('system.themes_page');

    }
    throw new AccessDeniedHttpException();
  }

  /**
   * Checks if confirmation forms and module installation is needed.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object possibly containing modules to be installed.
   * @param string $theme
   *   The theme being installed.
   * @param bool|null $set_default
   *   If the theme to install should be the default theme.
   *
   * @return array|null
   *   A form array, when a confirmation form is deemed necessary.
   */
  protected function checkConfirmationFormAndModules(Request $request, $theme, $set_default = NULL) {
    $modules_to_add = $request->query->get('modules');
    $modules = !empty($modules_to_add) ? $this->buildModuleList($modules_to_add) : NULL;
    $experimental_theme = $this->willInstallExperimentalTheme($theme);
    if ($this->willInstallExperimentalTheme($theme) || !empty($modules['experimental']) || !empty($modules['dependencies'])) {
      return $this->formBuilder()->getForm(ExtensionConfirmForm::class, 'system.themes_page', $modules, $theme, $set_default, $experimental_theme);
    }

    if (!empty($modules)) {
      $this->installModules($modules);
    }
  }

}
