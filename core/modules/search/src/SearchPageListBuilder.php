<?php

namespace Drupal\search;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\ConfigFormBaseTrait;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of search page entities.
 *
 * @see \Drupal\search\Entity\SearchPage
 */
class SearchPageListBuilder extends DraggableListBuilder implements FormInterface {
  use ConfigFormBaseTrait;

  /**
   * Stores the configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The search manager.
   *
   * @var \Drupal\search\SearchPluginManager
   */
  protected $searchManager;

  /**
   * The search index.
   *
   * @var \Drupal\search\SearchIndexInterface
   */
  protected $searchIndex;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new SearchPageListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\search\SearchPluginManager $search_manager
   *   The search plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\search\SearchIndexInterface $search_index
   *   The search index.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, SearchPluginManager $search_manager, ConfigFactoryInterface $config_factory, MessengerInterface $messenger, SearchIndexInterface $search_index) {
    parent::__construct($entity_type, $storage);
    $this->configFactory = $config_factory;
    $this->searchManager = $search_manager;
    $this->messenger = $messenger;
    $this->searchIndex = $search_index;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('plugin.manager.search'),
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('search.index')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['search.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = [
      'data' => $this->t('Label'),
    ];
    $header['url'] = [
      'data' => $this->t('URL'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['plugin'] = [
      'data' => $this->t('Type'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['status'] = [
      'data' => $this->t('Status'),
    ];
    $header['progress'] = [
      'data' => $this->t('Indexing progress'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\search\SearchPageInterface $entity */
    $row['label'] = $entity->label();
    $row['url']['#markup'] = 'search/' . $entity->getPath();
    // If the search page is active, link to it.
    if ($entity->status()) {
      $row['url'] = [
        '#type' => 'link',
        '#title' => $row['url'],
        '#url' => Url::fromRoute('search.view_' . $entity->id()),
      ];
    }

    $definition = $entity->getPlugin()->getPluginDefinition();
    $row['plugin']['#markup'] = $definition['title'];

    if ($entity->isDefaultSearch()) {
      $status = $this->t('Default');
    }
    elseif ($entity->status()) {
      $status = $this->t('Enabled');
    }
    else {
      $status = $this->t('Disabled');
    }
    $row['status']['#markup'] = $status;

    if ($entity->isIndexable()) {
      $status = $entity->getPlugin()->indexStatus();
      $row['progress']['#markup'] = $this->t('%num_indexed of %num_total indexed', [
        '%num_indexed' => $status['total'] - $status['remaining'],
        '%num_total' => $status['total'],
      ]);
    }
    else {
      $row['progress']['#markup'] = $this->t('Does not use index');
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['search_pages'] = [
      '#type' => 'details',
      '#title' => $this->t('Search pages'),
      '#open' => TRUE,
    ];
    $form['search_pages']['add_page'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];
    // In order to prevent validation errors for the parent form, this cannot be
    // required, see self::validateAddSearchPage().
    $form['search_pages']['add_page']['search_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Search page type'),
      '#empty_option' => $this->t('- Choose page type -'),
      '#options' => array_map(function ($definition) {
        return $definition['title'];
      }, $this->searchManager->getDefinitions()),
    ];
    $form['search_pages']['add_page']['add_search_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add search page'),
      '#validate' => ['::validateAddSearchPage'],
      '#submit' => ['::submitAddSearchPage'],
      '#limit_validation_errors' => [['search_type']],
    ];

    // Move the listing into the search_pages element.
    $form['search_pages'][$this->entitiesKey] = $form[$this->entitiesKey];
    $form['search_pages'][$this->entitiesKey]['#empty'] = $this->t('No search pages have been configured.');
    unset($form[$this->entitiesKey]);

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\search\SearchPageInterface $entity */
    $operations = parent::getDefaultOperations($entity);

    // Prevent the default search from being disabled or deleted.
    if ($entity->isDefaultSearch()) {
      unset($operations['disable'], $operations['delete']);
    }
    else {
      $operations['default'] = [
        'title' => $this->t('Set as default'),
        'url' => Url::fromRoute('entity.search_page.set_default', [
          'search_page' => $entity->id(),
        ]),
        'weight' => 50,
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
  }

  /**
   * Form validation handler for adding a new search page.
   */
  public function validateAddSearchPage(array &$form, FormStateInterface $form_state): void {
    if ($form_state->isValueEmpty('search_type')) {
      $form_state->setErrorByName('search_type', $this->t('You must select the new search page type.'));
    }
  }

  /**
   * Form submission handler for adding a new search page.
   */
  public function submitAddSearchPage(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect(
      'search.add_type',
      ['search_plugin_id' => $form_state->getValue('search_type')]
    );
  }

}
