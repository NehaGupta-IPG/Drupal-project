<?php

namespace Drupal\rest\Plugin\views\style;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rest\Plugin\views\display\RestExport;
use Drupal\views\Attribute\ViewsStyle;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 */
#[ViewsStyle(
  id: "serializer",
  title: new TranslatableMarkup("Serializer"),
  help: new TranslatableMarkup("Serializes views row data using the Serializer component."),
  display_types: ["data"],
)]
class Serializer extends StylePluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * The serializer which serializes the views result.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The available serialization formats.
   *
   * @var array
   */
  protected $formats = [];

  /**
   * The serialization format providers, keyed by format.
   *
   * @var string[]
   */
  protected $formatProviders;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('serializer'),
      $container->getParameter('serializer.formats'),
      $container->getParameter('serializer.format_providers')
    );
  }

  /**
   * Constructs a Plugin object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, SerializerInterface $serializer, array $serializer_formats, array $serializer_format_providers) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->definition = $plugin_definition + $configuration;
    $this->serializer = $serializer;
    $this->formats = $serializer_formats;
    $this->formatProviders = $serializer_format_providers;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['formats'] = ['default' => []];
    $options['pager'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['formats'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Accepted request formats'),
      '#description' => $this->t('Request formats that will be allowed in responses. If none are selected all formats will be allowed.'),
      '#options' => $this->getFormatOptions(),
      '#default_value' => $this->options['formats'],
    ];
    $form['pager'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add pager information'),
      '#description' => $this->t('Pager information will be added to rest export. <b>Output structure will be changed.</b>'),
      '#default_value' => $this->options['pager'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    $formats = $form_state->getValue(['style_options', 'formats']);
    $form_state->setValue(['style_options', 'formats'], array_filter($formats));
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = [];
    // If the Data Entity row plugin is used, this will be an array of entities
    // which will pass through Serializer to one of the registered Normalizers,
    // which will transform it to arrays/scalars. If the Data field row plugin
    // is used, $rows will not contain objects and will pass directly to the
    // Encoder.
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows[] = $this->view->rowPlugin->render($row);
    }
    unset($this->view->row_index);

    // Get the content type configured in the display or fallback to the
    // default.
    if ((empty($this->view->live_preview))) {
      $content_type = $this->displayHandler->getContentType();
    }
    else {
      $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
    }

    $result = $rows;
    if ($this->options['pager'] && $this->view->pager && $this->displayHandler instanceof RestExport) {
      $result = $this->wrapRowsInPager($rows);
    }

    return $this->serializer->serialize($result, $content_type, ['views_style_plugin' => $this]);
  }

  /**
   * Wraps an array of rows in another array with page info.
   *
   * @param array $rows
   *   Array with the rows to wrap.
   *
   * @return array
   *   An array with the following keys:
   *   - rows: the original rows array.
   *   - pager: an array with the following keys:
   *     - current_page: the zero-based current page number.
   *     - items_per_page: the number of items displayed per page.
   *     - total_items: the total number of items in the view result.
   *     - total_pages: the total number of pages.
   */
  protected function wrapRowsInPager(array $rows): array {
    $pager = $this->view->pager;
    $items_per_page = (int) $pager->getItemsPerPage();

    if ($pager->useCountQuery()) {
      $current_page = (int) $pager->getCurrentPage();
      $total_items = (int) $pager->getTotalItems();
    }
    else {
      // Pagers without a count query (e.g. "some") do not track current page
      // or total items accurately, so read them from the request and query.
      $total_items = (int) $pager->getTotalItems();
      if (isset($this->view->build_info['count_query'])) {
        $count_query = clone $this->view->build_info['count_query'];
        $count_query->preExecute();
        $total_items = (int) $count_query->countQuery()->execute()->fetchField();
      }
      $current_page = $this->view->getRequest()->query->getInt('page');
    }

    $total_pages = ($items_per_page > 0)
      ? (int) ceil($total_items / $items_per_page)
      : 1;

    return [
      'rows' => $rows,
      'pager' => [
        'current_page' => $current_page,
        'items_per_page' => $items_per_page,
        'total_items' => $total_items,
        'total_pages' => $total_pages,
      ],
    ];
  }

  /**
   * Gets a list of all available formats that can be requested.
   *
   * This will return the configured formats, or all formats if none have been
   * selected.
   *
   * @return array
   *   An array of formats.
   */
  public function getFormats() {
    return $this->options['formats'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = ['request_format'];
    if ($this->options['pager']) {
      $contexts[] = 'url.query_args:page';
    }
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $formats = $this->getFormats();
    $providers = array_intersect_key($this->formatProviders, array_flip($formats));
    // The plugin always uses services from the serialization module.
    $providers[] = 'serialization';

    $dependencies += ['module' => []];
    $dependencies['module'] = array_merge($dependencies['module'], $providers);
    return $dependencies;
  }

  /**
   * Returns an array of format options.
   *
   * @return string[]
   *   An array of format options. Both key and value are the same.
   */
  protected function getFormatOptions() {
    $formats = array_keys($this->formatProviders);
    return array_combine($formats, $formats);
  }

}
