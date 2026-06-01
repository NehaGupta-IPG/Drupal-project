<?php

namespace Drupal\taxonomy\Plugin\views\filter;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\taxonomy\VocabularyStorageInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by term id.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter("taxonomy_index_tid")]
class TaxonomyIndexTid extends ManyToOne {

  /**
   * Stores the exposed input for this filter.
   *
   * @var array|null
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $validated_exposed_input = NULL;

  /**
   * The vocabulary storage.
   *
   * @var \Drupal\taxonomy\VocabularyStorageInterface
   */
  protected $vocabularyStorage;

  /**
   * The term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a TaxonomyIndexTid object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\taxonomy\VocabularyStorageInterface $vocabulary_storage
   *   The vocabulary storage.
   * @param \Drupal\taxonomy\TermStorageInterface $term_storage
   *   The term storage.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, VocabularyStorageInterface $vocabulary_storage, TermStorageInterface $term_storage, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->vocabularyStorage = $vocabulary_storage;
    $this->termStorage = $term_storage;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('taxonomy_vocabulary'),
      $container->get('entity_type.manager')->getStorage('taxonomy_term'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    if (!empty($this->definition['vocabulary'])) {
      $this->options['vids'] = [$this->definition['vocabulary']];
    }
  }

  public function hasExtraOptions() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    return $this->valueOptions;
  }

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['type'] = ['default' => 'textfield'];
    $options['limit'] = ['default' => TRUE];
    $options['vids'] = ['default' => []];
    $options['hierarchy'] = ['default' => FALSE];
    $options['error_message'] = ['default' => TRUE];

    return $options;
  }

  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state) {
    if ($this->options['limit']) {
      // We only do this when the form is displayed.
      $vocabularies = $this->vocabularyStorage->loadMultiple();

      if (empty($this->definition['vocabulary'])) {
        $form['vids'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Vocabulary'),
          '#options' => $this->getVocabularyLabels($vocabularies),
          '#description' => $this->t('Select which vocabularies to show terms for in the regular options.'),
          '#default_value' => $this->options['vids'],
          '#required' => TRUE,
        ];
      }
    }

    $form['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Selection type'),
      '#options' => ['select' => $this->t('Dropdown'), 'textfield' => $this->t('Autocomplete')],
      '#default_value' => $this->options['type'],
    ];

    $form['hierarchy'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show hierarchy in dropdown'),
      '#default_value' => !empty($this->options['hierarchy']),
      '#states' => [
        'visible' => [
          ':input[name="options[type]"]' => ['value' => 'select'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitExtraOptionsForm($form, FormStateInterface $form_state): void {
    $vids = $form_state->getValue(['options', 'vids']);
    $form_state->setValue(['options', 'vids'], array_keys(array_filter($vids)));
  }

  protected function valueForm(&$form, FormStateInterface $form_state) {
    $vocabularies = $this->vocabularyStorage->loadMultiple($this->options['vids']);
    if (empty($vocabularies) && $this->options['limit']) {
      $form['markup'] = [
        '#markup' => '<div class="js-form-item form-item">' . $this->t('Invalid or no vocabularies are selected. Select valid vocabularies in filter settings.') . '</div>',
      ];
      return;
    }

    $form['value'] = [
      '#title' => $this->options['limit'] ? $this->formatPlural(count($vocabularies), 'Select terms from vocabulary @vocabulary_labels', 'Select terms from vocabularies @vocabulary_labels', [
        '@vocabulary_labels' => "'" . implode("', '", $this->getVocabularyLabels($vocabularies)) . "'",
      ]) : $this->t('Select terms'),
    ];

    if ($this->options['type'] == 'textfield') {
      $terms = $this->value ? $this->termStorage->loadMultiple($this->value) : [];
      $form['value'] += [
        '#type' => 'textfield',
        '#default_value' => EntityAutocomplete::getEntityLabels($terms),
      ];

      if ($this->options['limit']) {
        $form['value']['#type'] = 'entity_autocomplete';
        $form['value']['#target_type'] = 'taxonomy_term';
        $form['value']['#selection_settings']['target_bundles'] = array_keys($vocabularies);
        $form['value']['#tags'] = TRUE;
        $form['value']['#process_default_value'] = FALSE;
      }
    }
    else {
      $options = [];
      if (!empty($this->options['hierarchy']) && $this->options['limit']) {
        $terms = [];
        foreach ($vocabularies as $vocabulary) {
          $terms = array_merge($terms, array_filter(
            $this->termStorage->loadTree($vocabulary->id(), 0, NULL, TRUE), function (TermInterface $term): bool {
              return $term->access('view label');
            }
          ));
        }
      }
      else {
        $options = [];
        $query = $this->termStorage->getQuery()
          ->accessCheck()
          // @todo Sorting on vocabulary properties -
          //   https://www.drupal.org/node/1821274.
          ->sort('weight')
          ->sort('name')
          ->addTag('taxonomy_term_access');
        if (!$this->currentUser->hasPermission('administer taxonomy')) {
          $query->condition('status', 1);
        }
        if ($this->options['limit']) {
          $query->condition('vid', $this->options['vids'], 'IN');
        }
        $terms = $this->termStorage->loadMultiple($query->execute());
      }

      /** @var \Drupal\taxonomy\TermInterface[] $terms */
      foreach ($terms as $term) {
        $this->addOption($options, $term, $vocabularies);
      }

      $default_value = $this->value;

      if ($exposed = $form_state->get('exposed')) {
        $identifier = $this->options['expose']['identifier'];

        if (!empty($this->options['expose']['reduce'])) {
          $options = $this->reduceValueOptions($options);

          if (!empty($this->options['expose']['multiple']) && empty($this->options['expose']['required'])) {
            $default_value = [];
          }
        }

        if (empty($this->options['expose']['multiple'])) {
          if (empty($this->options['expose']['required']) && (empty($default_value) || !empty($this->options['expose']['reduce']))) {
            $default_value = 'All';
          }
          elseif (empty($default_value)) {
            $keys = array_keys($options);
            $default_value = array_shift($keys);
          }
          // Due to #1464174 there is a chance that array('') was saved in the admin ui.
          // Let's choose a safe default value.
          elseif ($default_value == ['']) {
            $default_value = 'All';
          }
          else {
            $copy = $default_value;
            $default_value = array_shift($copy);
          }
        }
      }
      $form['value'] += [
        '#type' => 'select',
        '#multiple' => TRUE,
        '#options' => $options,
        '#size' => min(9, count($options, COUNT_RECURSIVE)),
        '#default_value' => $default_value,
      ];

      $user_input = $form_state->getUserInput();
      if ($exposed && isset($identifier) && !isset($user_input[$identifier])) {
        $user_input[$identifier] = $default_value;
        $form_state->setUserInput($user_input);
      }
    }

    if (!$form_state->get('exposed')) {
      // Retain the helper option
      $this->helper->buildOptionsForm($form, $form_state);

      // Show help text if not exposed to end users.
      $form['value']['#description'] = $this->t('Leave blank for all. Otherwise, the first selected term will be the default instead of "Any".');
    }
  }

  protected function valueValidate($form, FormStateInterface $form_state) {
    // We only validate if they've chosen the text field style.
    if ($this->options['type'] != 'textfield') {
      return;
    }

    $tids = [];
    if ($values = $form_state->getValue(['options', 'value'])) {
      foreach ($values as $value) {
        $tids[] = $value['target_id'];
      }
    }
    $form_state->setValue(['options', 'value'], $tids);
  }

  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }
    // We need to know the operator, which is normally set in
    // \Drupal\views\Plugin\views\filter\FilterPluginBase::acceptExposedInput(),
    // before we actually call the parent version of ourselves.
    if (!empty($this->options['expose']['use_operator']) && !empty($this->options['expose']['operator_id']) && isset($input[$this->options['expose']['operator_id']])) {
      $this->operator = $input[$this->options['expose']['operator_id']];
    }

    // If view is an attachment and is inheriting exposed filters, then assume
    // exposed input has already been validated
    if (!empty($this->view->is_attachment) && $this->view->display_handler->usesExposed()) {
      $this->validated_exposed_input = (array) $this->view->exposed_raw_input[$this->options['expose']['identifier']];
    }

    // If we're checking for EMPTY or NOT, we don't need any input, and we can
    // say that our input conditions are met by just having the right operator.
    if ($this->operator == 'empty' || $this->operator == 'not empty') {
      return TRUE;
    }

    // If it's non-required and there's no value don't bother filtering.
    if (!$this->options['expose']['required'] && empty($this->validated_exposed_input)) {
      return FALSE;
    }

    $rc = parent::acceptExposedInput($input);
    if ($rc) {
      // If we have previously validated input, override.
      if (isset($this->validated_exposed_input)) {
        $this->value = $this->validated_exposed_input;
      }
    }

    return $rc;
  }

  public function validateExposed(&$form, FormStateInterface $form_state) {
    if (empty($this->options['exposed'])) {
      return;
    }

    $identifier = $this->options['expose']['identifier'];
    $input = $form_state->getValue($identifier);

    if ($this->options['is_grouped'] && isset($this->options['group_info']['group_items'][$input])) {
      $this->validated_exposed_input = $this->options['group_info']['group_items'][$input]['value'];
      return;
    }

    // We only validate if they've chosen the text field style.
    if ($this->options['type'] != 'textfield') {
      if ($form_state->getValue($identifier) != 'All') {
        $this->validated_exposed_input = (array) $form_state->getValue($identifier);
      }
      return;
    }

    if (empty($this->options['expose']['identifier'])) {
      return;
    }

    if ($values = $form_state->getValue($identifier)) {
      foreach ($values as $value) {
        $this->validated_exposed_input[] = $value['target_id'];
      }
    }
  }

  protected function valueSubmit($form, FormStateInterface $form_state) {
    // Prevent array_filter from messing up our arrays in parent submit.
  }

  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);
    if ($this->options['type'] != 'select') {
      unset($form['expose']['reduce']);
    }
    $form['error_message'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display error message'),
      '#default_value' => !empty($this->options['error_message']),
    ];
  }

  public function adminSummary() {
    // Set up $this->valueOptions for the parent summary
    $this->valueOptions = [];

    if ($this->value) {
      $this->value = array_filter($this->value);
      $terms = $this->termStorage->loadMultiple($this->value);
      foreach ($terms as $term) {
        $this->valueOptions[$term->id()] = \Drupal::service('entity.repository')->getTranslationFromContext($term)->label();
      }
    }
    return parent::adminSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    $vocabularies = $this->vocabularyStorage->loadMultiple($this->options['vids']);
    foreach ($vocabularies as $vocabulary) {
      $dependencies[$vocabulary->getConfigDependencyKey()][] = $vocabulary->getConfigDependencyName();
    }

    foreach ($this->termStorage->loadMultiple($this->options['value']) as $term) {
      $dependencies[$term->getConfigDependencyKey()][] = $term->getConfigDependencyName();
    }

    return $dependencies;
  }

  /**
   * Returns a list of vocabulary labels keyed by vocabulary ID.
   *
   * @param array $vocabularies
   *   An associative array of vocabulary entities, keyed by vocabulary ID.
   *
   * @return array
   *   Associative array of vocabulary labels keyed by vocabulary ID.
   */
  protected function getVocabularyLabels(array $vocabularies): array {
    return array_map(function (VocabularyInterface $vocabulary): string {
      return $vocabulary->label();
    }, $vocabularies);
  }

  /**
   * Adds an option to the filter settings select.
   *
   * @param array $options
   *   The list of select options passed by reference.
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term to be added as option.
   * @param array $vocabularies
   *   The list of vocabularies.
   */
  protected function addOption(array &$options, TermInterface $term, array $vocabularies): void {
    $option = \Drupal::service('entity.repository')->getTranslationFromContext($term)->label();
    if (!empty($this->options['hierarchy']) && $this->options['limit']) {
      $option = str_repeat('-', $term->depth) . $option;
    }

    /** @var \Drupal\taxonomy\VocabularyInterface[] $vocabularies */
    if (count($vocabularies) > 1) {
      $vocabulary_label = $vocabularies[$term->get('vid')->target_id]->label();
      $options[$vocabulary_label][$term->id()] = $option;
    }
    else {
      $options[$term->id()] = $option;
    }
  }

}
