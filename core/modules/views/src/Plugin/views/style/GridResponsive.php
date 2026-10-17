<?php

namespace Drupal\views\Plugin\views\style;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsStyle;

/**
 * Style plugin to render each item in a responsive grid cell.
 *
 * @ingroup views_style_plugins
 */
#[ViewsStyle(
  id: "grid_responsive",
  title: new TranslatableMarkup("Responsive Grid"),
  help: new TranslatableMarkup("Displays rows in a responsive grid."),
  theme: "views_view_grid_responsive",
  display_types: ["normal"],
)]
class GridResponsive extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['columns'] = ['default' => '4'];
    $options['cell_min_width'] = ['default' => '100'];
    $options['grid_gutter'] = ['default' => '10'];
    $options['alignment'] = ['default' => 'horizontal'];
    $options['grid_wrapper_class_custom'] = ['default' => ''];
    $options['grid_cell_class_custom'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['columns'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number of columns'),
      '#attributes' => ['style' => 'width: 6em;'],
      '#description' => $this->t('The maximum number of columns that will be displayed within the grid.'),
      '#default_value' => $this->options['columns'],
      '#required' => TRUE,
      '#min' => 1,
    ];
    $form['cell_min_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum grid cell width'),
      '#field_suffix' => 'px',
      '#attributes' => ['style' => 'width: 6em;'],
      '#description' => $this->t('The minimum width of the grid cells. If the grid container becomes narrow, the grid cells will reflow onto the next row as needed.'),
      '#default_value' => $this->options['cell_min_width'],
      '#required' => TRUE,
      '#min' => 1,
    ];
    $form['grid_gutter'] = [
      '#type' => 'number',
      '#title' => $this->t('Grid gutter spacing'),
      '#field_suffix' => 'px',
      '#attributes' => ['style' => 'width: 6em;'],
      '#description' => $this->t('The spacing between the grid cells.'),
      '#default_value' => $this->options['grid_gutter'],
      '#required' => TRUE,
      '#min' => 0,
    ];
    $form['alignment'] = [
      '#type' => 'radios',
      '#title' => $this->t('Alignment'),
      '#options' => ['horizontal' => $this->t('Horizontal'), 'vertical' => $this->t('Vertical')],
      '#default_value' => $this->options['alignment'],
      '#description' => $this->t('Horizontal alignment will place items starting in the upper left and moving right. Vertical alignment will place items starting in the upper left and moving down.'),
    ];
    $form['grid_wrapper_class_custom'] = [
      '#title' => $this->t('Custom grid wrapper classes'),
      '#description' => $this->t('Additional classes to provide on each grid wrapper. Separated by a space.'),
      '#type' => 'textfield',
      '#default_value' => $this->options['grid_wrapper_class_custom'],
    ];
    $form['grid_cell_class_custom'] = [
      '#title' => $this->t('Custom grid cell classes'),
      '#description' => $this->t('Additional classes to provide on each cell. Separated by a space.'),
      '#type' => 'textfield',
      '#default_value' => $this->options['grid_cell_class_custom'],
    ];
    if ($this->usesFields()) {
      $token_description = ' ' . $this->t('You may use field tokens as per the "Replacement patterns" used in "Rewrite the output of this field" for all fields.');
      $form['grid_wrapper_class_custom']['#description'] .= $token_description;
      $form['grid_cell_class_custom']['#description'] .= $token_description;
    }
  }

  /**
   * Returns the token-replaced wrapper/cell classes for the specified result.
   *
   * @param string $type
   *   The type of custom grid class, either "grid_wrapper" or "grid_cell".
   *
   * @return string
   *   A space-delimited string of classes.
   */
  public function getCustomClasses(string $type): string {
    $class = $this->options[$type . '_class_custom'];
    if ($this->usesFields() && $this->view->field) {
      $class = strip_tags($this->tokenizeValue($class, 0));
    }

    $classes = explode(' ', $class);
    foreach ($classes as &$class) {
      $class = Html::cleanCssIdentifier($class);
    }
    return implode(' ', $classes);
  }

}
