<?php

namespace Drupal\comment\Plugin\Field\FieldFormatter;

use Drupal\comment\CommentingStatus;
use Drupal\comment\FormLocation;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a default comment formatter.
 */
#[FieldFormatter(
  id: 'comment_default',
  label: new TranslatableMarkup('Comment list'),
  field_types: [
    'comment',
  ],
)]
class CommentDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'view_mode' => 'default',
      'pager_id' => 0,
      'use_entity_access_control' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * The comment storage.
   *
   * @var \Drupal\comment\CommentStorageInterface
   */
  protected $storage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The comment render controller.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $viewBuilder;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new CommentDefaultFormatter.
   *
   * @param string $plugin_id
   *   The plugin ID for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, EntityFormBuilderInterface $entity_form_builder, RouteMatchInterface $route_match, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->viewBuilder = $entity_type_manager->getViewBuilder('comment');
    $this->storage = $entity_type_manager->getStorage('comment');
    $this->currentUser = $current_user;
    $this->entityFormBuilder = $entity_form_builder;
    $this->routeMatch = $route_match;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $output = [];
    $cacheability = new CacheableMetadata();

    $field_name = $this->fieldDefinition->getName();
    $entity = $items->getEntity();

    $status = CommentingStatus::tryFrom((int) $items->status);

    if ($status != CommentingStatus::Hidden && empty($entity->in_preview) &&
      // Comments are added to the search results and search index by
      // comment_node_update_index() instead of by this formatter, so don't
      // return anything if the view mode is search_index or search_result.
      !in_array($this->viewMode, ['search_result', 'search_index'])) {
      $comment_settings = $this->getFieldSettings();

      if (!$this->getSetting('use_entity_access_control')) {
        // @phpstan-ignore method.deprecated
        return $this->getDeprecatedPermissionBasedAccessElements($entity, $field_name, $status, $comment_settings);
      }

      // Only attempt to render comments if the entity has visible comments.
      // Unpublished comments are not included in
      // $entity->get($field_name)->comment_count, but unpublished comments
      // should display if the user is an administrator.
      $comment_admin = $this->currentUser->hasPermission('administer comments');
      $cacheability->addCacheContexts(['user.permissions']);
      $items_access = $items->access('view comment list', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($items_access);
      if ($comment_admin || $items_access->isAllowed()) {
        $output['comments'] = [];

        if ($items->comment_count || $comment_admin) {
          $this->buildCommentList($output, $entity, $field_name);
        }
      }

      // Append comment form if the comments are open and the form is set to
      // display below the entity. Do not show the form for the print view mode.
      $form_location = FormLocation::tryFrom($comment_settings['form_location']);
      if ($status == CommentingStatus::Open && $form_location == FormLocation::Below && $this->viewMode != 'print') {
        // Inject a lazy builder which will show the add comment form if the
        // user has access.
        $output['comment_form'] = [
          '#lazy_builder' => [
            'comment.lazy_builders:renderForm',
            [
              $entity->getEntityTypeId(),
              $entity->id(),
              $field_name,
              $this->getFieldSetting('comment_type'),
            ],
          ],
          '#create_placeholder' => TRUE,
        ];
      }

      $elements[] = $output + [
        '#comment_type' => $this->getFieldSetting('comment_type'),
        '#comment_display_mode' => $this->getFieldSetting('default_mode'),
        'comments' => [],
        'comment_form' => [],
      ];
    }

    $cacheability->applyTo($elements);
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $view_modes = $this->getViewModes();
    $element['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Comments view mode'),
      '#description' => $this->t('Select the view mode used to show the list of comments.'),
      '#default_value' => $this->getSetting('view_mode'),
      '#options' => $view_modes,
      // Only show the select element when there are more than one options.
      '#access' => count($view_modes) > 1,
    ];
    $element['pager_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Pager ID'),
      '#options' => range(0, 10),
      '#default_value' => $this->getSetting('pager_id'),
      '#description' => $this->t("Unless you're experiencing problems with pagers related to this field, you should leave this at 0. If using multiple pagers on one page you may need to set this number to a higher value so as not to conflict within the ?page= array. Large values will add a lot of commas to your URLs, so avoid if possible."),
    ];
    $element['use_entity_access_control'] = [
      '#type' => 'radios',
      '#title' => $this->t('How to check the user access'),
      '#options' => [
        1 => $this->t('Use entity access control'),
        0 => $this->t('Use permission-based access control (deprecated)'),
      ],
      '#description' => $this->t('Permission-based access control is deprecated and will be removed in Drupal 13.0.0.'),
      '#default_value' => (int) $this->getSetting('use_entity_access_control'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $view_mode = $this->getSetting('view_mode');
    $view_modes = $this->getViewModes();
    $view_mode_label = $view_modes[$view_mode] ?? 'default';
    $summary = [$this->t('Comment view mode: @mode', ['@mode' => $view_mode_label])];
    if ($pager_id = $this->getSetting('pager_id')) {
      $summary[] = $this->t('Pager ID: @id', ['@id' => $pager_id]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    if ($mode = $this->getSetting('view_mode')) {
      if ($bundle = $this->getFieldSetting('comment_type')) {
        /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
        if ($display = EntityViewDisplay::load("comment.$bundle.$mode")) {
          $dependencies[$display->getConfigDependencyKey()][] = $display->getConfigDependencyName();
        }
      }
    }
    return $dependencies;
  }

  /**
   * Provides a list of comment view modes for the configured comment type.
   *
   * @return array
   *   Associative array keyed by view mode key and having the view mode label
   *   as value.
   */
  protected function getViewModes() {
    return $this->entityDisplayRepository->getViewModeOptionsByBundle('comment', $this->getFieldSetting('comment_type'));
  }

  /**
   * Builds the list of comments.
   *
   * @param array $output
   *   The output array to which the comments will be added.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity for which the comments are being built.
   * @param string $field_name
   *   The name of the field for which the comments are being built.
   */
  protected function buildCommentList(array &$output, FieldableEntityInterface $entity, string $field_name): void {
    $mode = $this->getFieldSetting('default_mode');
    $comments_per_page = $this->getFieldSetting('per_page');
    $comments = $this->storage->loadThread($entity, $field_name, $mode, $comments_per_page, $this->getSetting('pager_id'));

    if ($comments) {
      $build = $this->viewBuilder->viewMultiple($comments, $this->getSetting('view_mode'));
      $build['pager']['#type'] = 'pager';
      // CommentController::commentPermalink() calculates the page number
      // where a specific comment appears and does a subrequest pointing
      // to that page, we need to pass that subrequest route to our pager
      // to keep the pager working.
      $build['pager']['#route_name'] = $this->routeMatch->getRouteName();
      $build['pager']['#route_parameters'] = $this->routeMatch->getRawParameters()->all();
      if ($this->getSetting('pager_id')) {
        $build['pager']['#element'] = $this->getSetting('pager_id');
      }
      $output['comments'] += $build;
    }
  }

  /**
   * Returns the elements by using the deprecated permission-based access.
   *
   * @deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Only entity
   *   access control will be available, and the field--comment.html.twig
   *   template overrides should be adapted.
   *
   * @see https://www.drupal.org/node/3180183
   */
  protected function getDeprecatedPermissionBasedAccessElements(FieldableEntityInterface $entity, string $field_name, CommentingStatus $status, array $comment_settings): array {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Only entity access control will be available, and the field--comment.html.twig template overrides should be adapted. See https://www.drupal.org/node/3180183', E_USER_DEPRECATED);

    $elements = [];
    $output = [];

    $elements['#cache']['contexts'][] = 'user.permissions';
    if ($this->currentUser->hasPermission('access comments') || $this->currentUser->hasPermission('administer comments')) {
      $output['comments'] = [];
      if ($entity->get($field_name)->comment_count || $this->currentUser->hasPermission('administer comments')) {
        $this->buildCommentList($output, $entity, $field_name);
      }
    }

    // Append comment form if the comments are open and the form is set to
    // display below the entity. Do not show the form for the print view mode.
    $form_location = FormLocation::tryFrom($comment_settings['form_location']);
    if ($status == CommentingStatus::Open && $form_location == FormLocation::Below && $this->viewMode != 'print') {
      // Only show the add comment form if the user has permission.
      $elements['#cache']['contexts'][] = 'user.roles';
      if ($this->currentUser->hasPermission('post comments')) {
        $output['comment_form'] = [
          '#lazy_builder' => [
            'comment.lazy_builders:renderForm',
            [
              $entity->getEntityTypeId(),
              $entity->id(),
              $field_name,
              $this->getFieldSetting('comment_type'),
              FALSE,
            ],
          ],
          '#create_placeholder' => TRUE,
        ];
      }
    }

    $elements[] = $output + [
      '#comment_type' => $this->getFieldSetting('comment_type'),
      '#comment_display_mode' => $this->getFieldSetting('default_mode'),
      'comments' => [],
      'comment_form' => [],
    ];

    return $elements;
  }

}
