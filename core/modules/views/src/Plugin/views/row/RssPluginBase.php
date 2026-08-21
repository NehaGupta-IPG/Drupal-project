<?php

namespace Drupal\views\Plugin\views\row;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Entity\Render\EntityTranslationRenderTrait;
use Drupal\views\ViewExecutable;

/**
 * Base class for Views RSS row plugins.
 */
abstract class RssPluginBase extends RowPluginBase {

  use EntityTranslationRenderTrait;

  /**
   * A fake view mode to only display titles.
   */
  const string TITLE_VIEW_MODE = '_views.rss.title';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The ID of the entity type for which this is an RSS row plugin.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Constructs a RssPluginBase  object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Language\LanguageManagerInterface|null $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface|null $entity_repository
   *   The entity repository.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository, ?LanguageManagerInterface $language_manager = NULL, ?EntityRepositoryInterface $entity_repository = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;

    if ($language_manager === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $language_manager argument is deprecated in drupal:12.0.0 and it will be required in drupal:13.0.0. See https://www.drupal.org/node/3618490', E_USER_DEPRECATED);
      $language_manager = \Drupal::service('language_manager');
    }

    $this->languageManager = $language_manager;

    if ($entity_repository === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $entity_repository argument is deprecated in drupal:12.0.0 and it will be required in drupal:13.0.0. See https://www.drupal.org/node/3618490', E_USER_DEPRECATED);
      $entity_repository = \Drupal::service('entity.repository');
    }

    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();

    // Select the rss view mode by default, otherwise select the first available
    // view mode.
    $view_modes = $this->entityDisplayRepository->getViewModes($this->entityTypeId);
    if (isset($view_modes['rss'])) {
      $options['view_mode'] = ['default' => 'rss'];
    }
    else {
      $options['view_mode'] = ['default' => key($view_modes)];
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Display type'),
      '#options' => $this->buildOptionsForm_summary_options(),
      '#default_value' => $this->options['view_mode'],
    ];
  }

  /**
   * Return the main options, which are shown in the summary title.
   */
  public function buildOptionsForm_summary_options(): array {
    $view_modes = $this->entityDisplayRepository->getViewModes($this->entityTypeId);
    $options = [];
    foreach ($view_modes as $mode => $settings) {
      $options[$mode] = $settings['label'];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();

    try {
      $view_mode = $this->entityTypeManager
        ->getStorage('entity_view_mode')
        ->load($this->entityTypeId . '.' . $this->options['view_mode']);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      \Drupal::logger('views')->error('Could not load entity view mode %view_mode', [
        '%view_mode' => $this->entityTypeId . '.' . $this->options['view_mode'],
        'exception' => $e,
      ]);

      $view_mode = NULL;
    }

    if ($view_mode) {
      $dependencies[$view_mode->getConfigDependencyKey()][] = $view_mode->getConfigDependencyName();
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    parent::query();

    $this->getEntityTranslationRenderer()->query($this->view->getQuery());
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): string {
    return $this->entityTypeId;
  }

  /**
   * Returns the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager;
  }

  /**
   * Returns the entity repository.
   *
   * @return \Drupal\Core\Entity\EntityRepositoryInterface
   *   The entity repository.
   */
  protected function getEntityRepository(): EntityRepositoryInterface {
    return $this->entityRepository;
  }

  /**
   * {@inheritdoc}
   */
  protected function getLanguageManager() {
    return $this->languageManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getView(): ?ViewExecutable {
    return $this->view;
  }

}
