<?php

namespace Drupal\views\Plugin\views\area;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\HandlerBase;

/**
 * @defgroup views_area_handlers Views area handler plugins
 * @{
 * Plugins governing areas of views, such as header, footer, and empty text.
 *
 * Area handler plugins extend \Drupal\views\Plugin\views\area\AreaPluginBase.
 * They must be attributed with \Drupal\views\Attribute\ViewsArea attribute,
 * and they must be in namespace directory Plugin\views\area.
 *
 * @ingroup views_plugins
 * @see plugin_api
 */

/**
 * Base class for area handler plugins.
 */
abstract class AreaPluginBase extends HandlerBase {

  /**
   * The type of this area handler, i.e. 'header', 'footer', or 'empty'.
   *
   * @var string
   */
  public $areaType;

  /**
   * Overrides Drupal\views\Plugin\views\HandlerBase::init().
   *
   * Make sure that no result area handlers are set to be shown when the result
   * is empty.
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    if ($this->areaType == 'empty') {
      $this->options['empty'] = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $this->definition['field'] = !empty($this->definition['field']) ? $this->definition['field'] : '';
    $label = !empty($this->definition['label']) ? $this->definition['label'] : $this->definition['field'];
    $options['admin_label']['default'] = $label;
    $options['empty'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return $this->adminLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    if ($form_state->get('type') != 'empty') {
      $form['empty'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display even if view has no result'),
        '#default_value' => $this->options['empty'] ?? 0,
      ];
    }

    $form['area_id'] = [
      '#type' => 'details',
      '#title' => $this->t('Machine name of the area'),
      '#weight' => 151,
    ];

    $form['area_id']['area_id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name of the area'),
      '#default_value' => $this->options['new_id'] ?? $form_state->get('id'),
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => [$this, 'exists'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    $current_area_id = $form_state->get('id');

    if ($form_state->getValue(['options', 'area_id', 'area_id']) != $current_area_id) {
      $this->options['new_id'] = $form_state->getValue(['options', 'area_id', 'area_id']);
    }
    else {
      unset($this->options['new_id']);
    }
  }

  /**
   * Performs any operations needed before full rendering.
   *
   * @param array $results
   *   The results of the view.
   */
  public function preRender(array $results) {
  }

  /**
   * Render the area.
   *
   * @param bool $empty
   *   (optional) Indicator if view result is empty or not. Defaults to FALSE.
   *
   * @return array
   *   In any case we need a valid Drupal render array to return.
   */
  abstract public function render($empty = FALSE);

  /**
   * Does that area have nothing to show.
   *
   * This method should be overridden by more complex handlers where the output
   * is not static and maybe itself be empty if it's rendered.
   *
   * @return bool
   *   Return TRUE if the area is empty, else FALSE.
   */
  public function isEmpty() {
    return empty($this->options['empty']);
  }

  /**
   * @param string $area_id
   * @return bool
   */
  public function exists(string $area_id): bool {
    $handlers = $this->displayHandler->getHandlers($this->areaType);

    // Make sure no another handler is already using the id, or being renamed, using the same id.
    return array_key_exists($area_id, $handlers) || !empty(
      array_filter(
        $handlers,
        fn ($handler) => isset($handler->options['new_id']) && $handler->options['new_id'] == $area_id
      )
    );
  }

}

/**
 * @}
 */
