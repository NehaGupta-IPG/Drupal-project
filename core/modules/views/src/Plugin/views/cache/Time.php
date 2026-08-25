<?php

namespace Drupal\views\Plugin\views\cache;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsCache;

/**
 * Simple caching of query results for Views displays.
 *
 * @ingroup views_cache_plugins
 */
#[ViewsCache(
  id: 'time',
  title: new TranslatableMarkup('Time-based'),
  help: new TranslatableMarkup('Simple time-based caching of data.'),
)]
class Time extends CachePluginBase {

  /**
   * The lifespans in seconds offered as preset options in the UI.
   */
  protected const array LIFESPAN_PRESETS = [60, 300, 1800, 3600, 21600, 518400];

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a Time cache plugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DateFormatterInterface $date_formatter, protected TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['results_lifespan'] = ['default' => 3600];
    $options['output_lifespan'] = ['default' => 3600];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $options = array_map([$this->dateFormatter, 'formatInterval'], array_combine(static::LIFESPAN_PRESETS, static::LIFESPAN_PRESETS));
    $options = [0 => $this->t('Never cache')] + $options + ['custom' => $this->t('Custom')];

    $results_lifespan = (int) $this->options['results_lifespan'];
    $form['results_lifespan'] = [
      '#type' => 'select',
      '#title' => $this->t('Query results'),
      '#description' => $this->t('The length of time raw query results should be cached.'),
      '#options' => $options,
      '#default_value' => isset($options[$results_lifespan]) ? $results_lifespan : 'custom',
    ];
    $form['results_lifespan_custom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Seconds'),
      '#size' => '25',
      '#maxlength' => '30',
      '#description' => $this->t('Length of time in seconds raw query results should be cached.'),
      '#default_value' => isset($options[$results_lifespan]) ? 0 : $results_lifespan,
      '#states' => [
        'visible' => [
          ':input[name="cache_options[results_lifespan]"]' => ['value' => 'custom'],
        ],
      ],
    ];
    $output_lifespan = (int) $this->options['output_lifespan'];
    $form['output_lifespan'] = [
      '#type' => 'select',
      '#title' => $this->t('Rendered output'),
      '#description' => $this->t('The length of time rendered HTML output should be cached.'),
      '#options' => $options,
      '#default_value' => isset($options[$output_lifespan]) ? $output_lifespan : 'custom',
    ];
    $form['output_lifespan_custom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Seconds'),
      '#size' => '25',
      '#maxlength' => '30',
      '#description' => $this->t('Length of time in seconds rendered HTML output should be cached.'),
      '#default_value' => isset($options[$output_lifespan]) ? 0 : $output_lifespan,
      '#states' => [
        'visible' => [
          ':input[name="cache_options[output_lifespan]"]' => ['value' => 'custom'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    $custom_fields = ['output_lifespan', 'results_lifespan'];
    foreach ($custom_fields as $field) {
      $cache_options = $form_state->getValue('cache_options');
      if ($cache_options[$field] === 'custom' && !is_numeric($cache_options[$field . '_custom'])) {
        $form_state->setError($form[$field . '_custom'], $this->t('Custom time values must be numeric.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state): void {
    $cache_options = $form_state->getValue('cache_options');
    foreach (['results_lifespan', 'output_lifespan'] as $field) {
      if ($cache_options[$field] === 'custom') {
        $cache_options[$field] = (int) ($cache_options[$field . '_custom'] ?? 0);
      }
      else {
        $cache_options[$field] = (int) $cache_options[$field];
      }
      unset($cache_options[$field . '_custom']);
    }
    $form_state->setValue('cache_options', $cache_options);
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    $results_lifespan = $this->getLifespan('results');
    $output_lifespan = $this->getLifespan('output');
    return $this->dateFormatter->formatInterval($results_lifespan, 1) . '/' . $this->dateFormatter->formatInterval($output_lifespan, 1);
  }

  /**
   * Gets the value for the lifespan of the given type.
   *
   * @param string $type
   *   The cache type, either 'results' or 'output'.
   *
   * @return int
   *   The lifespan in seconds.
   */
  protected function getLifespan($type) {
    return (int) $this->options[$type . '_lifespan'];
  }

  /**
   * {@inheritdoc}
   */
  protected function cacheExpire($type) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement. See https://www.drupal.org/node/3576855', E_USER_DEPRECATED);
    $lifespan = $this->getLifespan($type);
    if ($lifespan) {
      $cutoff = $this->time->getRequestTime() - $lifespan;
      return $cutoff;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function cacheSetMaxAge($type) {
    $lifespan = $this->getLifespan($type);
    if ($lifespan) {
      return $lifespan;
    }
    else {
      return Cache::PERMANENT;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultCacheMaxAge() {
    // The max age, unless overridden by some other piece of the rendered code
    // is determined by the output time setting.
    return (int) $this->cacheSetMaxAge('output');
  }

}
