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
    return ['theme' => []] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // @todo Remove the conversion of a string 'theme' value in Drupal 13.0.0.
    // @see https://www.drupal.org/node/3523553
    if (isset($configuration['theme']) && is_string($configuration['theme'])) {
      @trigger_error('Passing a string as the "theme" configuration value of the "current_theme" condition is deprecated in drupal:12.0.0 and is removed in drupal:13.0.0. Use an array of theme machine names instead. See https://www.drupal.org/node/3523553', E_USER_DEPRECATED);
      $configuration['theme'] = $configuration['theme'] === '' ? [] : [$configuration['theme']];
    }
    parent::setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['theme'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Themes'),
      '#default_value' => $this->configuration['theme'],
      '#options' => $this->getAllAvailableThemeOptions(),
      '#description' => $this->t('Select the themes to enforce. If none are selected, all themes will be allowed.'),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['theme'] = array_filter($form_state->getValue('theme', []));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    // If no themes are configured, the condition always evaluates to TRUE.
    if (empty($this->configuration['theme'])) {
      return TRUE;
    }
    // Return whether the current theme is in the currently configured themes:
    return in_array($this->themeManager->getActiveTheme()->getName(), $this->configuration['theme'], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $all_themes = $this->getAllAvailableThemeOptions();
    $result = empty($this->configuration['theme']) ? array_keys($all_themes) : $this->configuration['theme'];
    $count = count($result);
    $themes = implode(', ', array_intersect_key($all_themes, array_flip($result)));
    if (!empty($this->configuration['negate'])) {
      return $this->formatPlural($count, 'The current theme is not: @themes', 'The current theme is not one of the following: @themes', ['@themes' => $themes]);
    }
    return $this->formatPlural($count, 'The current theme is: @themes', 'The current theme is one of the following: @themes', ['@themes' => $themes]);
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
   * Retrieves a list of all available themes.
   *
   * @return array
   *   An associative array of themes, where the keys are the theme
   *   machine names and the values are the displayed theme names.
   */
  protected function getAllAvailableThemeOptions(): array {
    return array_map(function ($theme_info) {
      return $theme_info->info['name'];
    }, $this->themeHandler->listInfo());
  }

}
