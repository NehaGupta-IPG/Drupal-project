<?php

namespace Drupal\views\Plugin\views\area;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsArea;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views area text handler.
 *
 * @ingroup views_area_handlers
 */
#[ViewsArea("text")]
class Text extends TokenizeAreaPluginBase {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['content'] = [
      'contains' => [
        'value' => ['default' => ''],
        'format' => ['default' => NULL],
      ],
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['content'] = [
      '#title' => $this->t('Content'),
      '#type' => 'text_format',
      '#default_value' => $this->options['content']['value'],
      '#rows' => 6,
      '#format' => $this->getFormatId(),
      '#editor' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preQuery() {
    $content = $this->options['content']['value'];
    // Check for tokens that require a total row count.
    if (str_contains($content, '[view:page-count]') || str_contains($content, '[view:total-rows]')) {
      $this->view->get_total_rows = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    $format = $this->getFormatId();
    if (!$empty || !empty($this->options['empty'])) {
      return [
        '#type' => 'processed_text',
        '#text' => $this->tokenizeValue($this->options['content']['value']),
        '#format' => $format,
      ];
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $format = $this->entityTypeManager->getStorage('filter_format')
      ->load($this->getFormatId());
    return [
      $format->getConfigDependencyKey() => [$format->getConfigDependencyName()],
    ];
  }

  /**
   * Returns the ID of the text format used for this area.
   *
   * @return string
   *   The text format ID.
   */
  protected function getFormatId() {
    return $this->options['content']['format'] ?? filter_default_format();
  }

}
