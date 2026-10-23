<?php

namespace Drupal\system\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Provides a 'Current Theme' condition.
 */
#[Condition(
  id: "current_theme",
  label: new TranslatableMarkup("Current Theme"),
)]
class CurrentThemeCondition extends ConditionPluginBase {

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
   * phpcs:ignore Commenting.FunctionComment
   */
  #[Required]
  public function setThemeManager(ThemeManagerInterface $themeManager): void {
    $this->themeManager = $themeManager;
  }

  /**
   * phpcs:ignore Commenting.FunctionComment
   */
  #[Required]
  public function setThemeHandler(ThemeHandlerInterface $themeHandler): void {
    $this->themeHandler = $themeHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['theme' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#default_value' => $this->configuration['theme'],
      '#options' => array_map(function ($theme_info) {
        return $theme_info->info['name'];
      }, $this->themeHandler->listInfo()),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['theme'] = $form_state->getValue('theme');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    if (!$this->configuration['theme']) {
      return TRUE;
    }

    return $this->themeManager->getActiveTheme()->getName() == $this->configuration['theme'];
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    if ($this->isNegated()) {
      return $this->t('The current theme is not @theme', ['@theme' => $this->configuration['theme']]);
    }

    return $this->t('The current theme is @theme', ['@theme' => $this->configuration['theme']]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'theme';
    return $contexts;
  }

}
