<?php

namespace Drupal\system\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Provides a 'Current Theme' condition.
 */
#[Condition(
  id: "current_theme",
  label: new TranslatableMarkup("Current Theme"),
)]
class CurrentThemeCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Constructs a CurrentThemeCondition condition plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ThemeManagerInterface $theme_manager, ThemeHandlerInterface $theme_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->themeManager = $theme_manager;
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['themes' => []] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['themes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Themes'),
      '#default_value' => $this->configuration['themes'],
      '#options' => $this->getAllAvailableThemeOptions(),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['themes'] = $form_state->getValue('themes');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    return $this->currentThemeAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->currentThemeAllowed() ? $this->t('The current theme is one of the enabled themes.') : $this->t('The current theme is not one of the enabled themes.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'theme';
    return $contexts;
  }

  /**
   * Checks if the current theme is allowed.
   *
   * @return bool
   *   TRUE if the current theme is allowed, FALSE otherwise.
   */
  protected function currentThemeAllowed() {
    $configuredThemes = $this->configuration['themes'];
    $allThemes = $this->getAllAvailableThemeOptions();

    $allowedThemes = $this->isNegated() ? array_diff_key($allThemes, $configuredThemes) : array_intersect_key($allThemes, $configuredThemes);
    return in_array($this->themeManager->getActiveTheme()->getName(), $allowedThemes);
  }

  /**
   * Retrieves a list of all available themes.
   *
   * @return array
   *   An associative array of themes, where the keys are the theme
   *   machine names and the values are the displayed theme names.
   */
  protected function getAllAvailableThemeOptions() {
    return array_map(function ($theme_info) {
      return $theme_info->info['name'];
    }, $this->themeHandler->listInfo());
  }

}
