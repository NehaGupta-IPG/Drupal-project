<?php

namespace Drupal\system\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\PreExistingConfigException;
use Drupal\Core\Config\UnmetDependenciesException;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds a confirmation form for enabling modules or themes.
 *
 * @internal
 */
class ExtensionConfirmForm extends ConfirmFormBase {

  use ExtensionFormTrait;
  use MessengerTrait;

  /**
   * An associative list of modules to enable or disable.
   *
   * @var array
   */
  protected $modules = [];

  /**
   * The theme to enable.
   *
   * @var string
   */
  protected $theme;

  /**
   * The route to redirect to on cancel or submit.
   *
   * @var string
   */
  protected $cancelRoute;

  /**
   * The experimental theme.
   *
   * @var string
   */
  protected $experimentalTheme;

  /**
   * The default theme.
   *
   * @var string
   */
  protected $setDefaultTheme;

  public function __construct(
    protected ThemeExtensionList $themeList,
    protected ThemeInstallerInterface $themeInstaller,
    protected KeyValueStoreExpirableInterface $keyValueExpirable,
    protected ModuleInstallerInterface $moduleInstaller,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Continue');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('extension.list.theme'),
      $container->get('theme_installer'),
      $container->get('keyvalue.expirable')->get('module_list'),
      $container->get('module_installer')
    );
  }

  /**
   * Updates form and messages for when an experimental theme is being enabled.
   *
   * @param array $form
   *   The form being updated.
   */
  protected function experimentalThemeFormElements(array &$form): void {
    $all_themes = $this->themeList->getList();
    $this->messenger()->addWarning($this->t('Experimental themes are provided for testing purposes only. Use at your own risk.'));

    $dependencies = array_keys($all_themes[$this->theme]->requires);

    // Filter so there are only theme dependencies.
    $dependencies = array_filter($dependencies, function ($key) use ($all_themes) {
      return isset($all_themes[$key]);
    });
    $themes = array_merge([$this->theme], $dependencies);
    $is_experimental = function ($theme) use ($all_themes) {
      return isset($all_themes[$theme]) && isset($all_themes[$theme]->info['experimental']) && $all_themes[$theme]->info['experimental'];
    };
    $get_label = function ($theme) use ($all_themes) {
      return $all_themes[$theme]->info['name'];
    };

    if (!empty($dependencies)) {
      // Display a list of required themes that have to be installed as well.
      $form['message']['#items'][] = $this->formatPlural(count($dependencies), 'You must enable the @required theme to install @theme.', 'You must enable the @required themes to install @theme.', [
        '@theme' => $get_label($this->theme),
        // It is safe to implode this because theme names are not translated
        // markup and so will not be double-escaped.
        '@required' => implode(', ', array_map($get_label, $dependencies)),
      ]);
    }
    // Add the list of experimental themes after any other messages.
    $form['message']['#items'][] = $this->t('The following themes are experimental: @themes', [
      '@themes' => implode(', ', array_map($get_label, array_filter($themes, $is_experimental))),
    ]);
  }

  /**
   * Updates form and messages for when an experimental module is being enabled.
   *
   * @param array $form
   *   The form being updated.
   */
  protected function experimentalModulesFormElements(array &$form): void {
    $this->messenger()->addWarning($this->t('<a href=":url">Experimental modules</a> are provided for testing purposes only. Use at your own risk.', [':url' => 'https://www.drupal.org/core/experimental']));
    $items[] = $this->t('The following modules are experimental: @modules', ['@modules' => implode(', ', array_values($this->modules['experimental']))]);
    $form['message']['#items'][] = $this->t('The following modules are experimental: @modules', ['@modules' => implode(', ', array_values($this->modules['experimental']))]);
  }

  /**
   * Updates form and messages for when an dependent module is being enabled.
   *
   * @param array $form
   *   The form being updated.
   */
  protected function dependentModulesFormElements(array &$form): void {
    foreach ($this->modules['dependencies'] as $module => $dependencies) {
      $form['message']['#items'][] = $this->formatPlural(count($dependencies), 'You must enable the @required module to install @module.', 'You must enable the @required modules to install @module.', [
        '@module' => $this->modules['install'][$module],
        // It is safe to implode this because module names are not translated
        // markup and so will not be double-escaped.
        '@required' => implode(', ', $dependencies),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->cancelRoute = !empty($form_state->getBuildInfo()['args'][0]) ? $form_state->getBuildInfo()['args'][0] : NULL;
    // If the build args are not empty, this was requested by ThemeController.
    // Populate instance variables from $form_state->getBuildInfo().
    if (!empty($form_state->getBuildInfo()['args'][0])) {
      $this->cancelRoute = !empty($form_state->getBuildInfo()['args'][0]) ? $form_state->getBuildInfo()['args'][0] : NULL;
      $this->modules = !empty($form_state->getBuildInfo()['args'][1]) ? $form_state->getBuildInfo()['args'][1] : NULL;
      $this->theme = !empty($form_state->getBuildInfo()['args'][2]) ? $form_state->getBuildInfo()['args'][2] : NULL;
      $this->setDefaultTheme = !empty($form_state->getBuildInfo()['args'][3]) ? $form_state->getBuildInfo()['args'][3] : NULL;
      $this->experimentalTheme = !empty($form_state->getBuildInfo()['args'][4]) ? $form_state->getBuildInfo()['args'][4] : NULL;
    }
    else {
      // This means the request came from the modules install page. Set the
      // cancel route to the modules page and populate $this->modules via a
      // value from tempstore.
      $this->cancelRoute = 'system.modules_list';

      $account = $this->currentUser()->id();
      // For info on why keyValueExpirable is used:
      // @see https://drupal.org/node/1990544
      $this->modules = $this->keyValueExpirable->get($account);
    }

    $form['message'] = [
      '#theme' => 'item_list',
      '#items' => [],
    ];
    if (!empty($this->experimentalTheme)) {
      $this->experimentalThemeFormElements($form);
    }
    if (!empty($this->modules['dependencies'])) {
      $this->dependentModulesFormElements($form);
    }
    if (!empty($this->modules['experimental'])) {
      $this->experimentalModulesFormElements($form);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    $questions = [];
    if (!empty($this->modules['dependencies'])) {
      $questions[] = $this->t('Some required modules must be enabled.');
    }
    if (!empty($this->modules['experimental'])) {
      $questions[] = $this->t('Are you sure you wish to enable experimental modules?');
    }
    if (!empty($this->experimentalTheme)) {
      $questions[] = $this->t('Are you sure you wish to install an experimental theme?');
    }

    return implode(" ", $questions);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url($this->cancelRoute);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'extension_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!empty($this->modules)) {
      if ($this->currentUser()->hasPermission('administer modules')) {
        $this->installModules($this->modules, TRUE);
      }
    }
    if (!empty($this->theme)) {
      if ($this->currentUser()->hasPermission('administer themes')) {
        $this->submitThemeInstall($form_state);
      }
    }

    $form_state->setRedirect($this->cancelRoute);
  }

  /**
   * Installs a themes.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function submitThemeInstall(FormStateInterface $form_state): void {
    $themes = $this->themeList->getList();
    $config = $this->configFactory()->getEditable('system.theme');
    try {
      $theme = $this->theme;
      if ($this->themeInstaller->install([$theme])) {
        if ($this->setDefaultTheme) {
          // Set the default theme.
          $config->set('default', $theme)->save();

          // The status message depends on whether an admin theme is currently
          // in use: an empty string means the admin theme is set to be the
          // default theme.
          $admin_theme = $config->get('admin');
          if (!empty($admin_theme) && $admin_theme !== $theme) {
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
          $this->messenger()->addStatus($this->t('The %theme theme has been installed.', ['%theme' => $themes[$theme]->info['name']]));
        }
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
  }

  /**
   * Checks access to this form.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    // Users with either the administer themes or administer modules may access
    // this form. Additional checks for specific permissions occur before this
    // form attempts to actually install a module or theme.
    return AccessResult::allowedIf($account->hasPermission('administer themes') || $account->hasPermission('administer modules'));
  }

}
