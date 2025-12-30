<?php

namespace Drupal\workspaces\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workspaces\WorkspaceTrackerInterface;
use Drupal\workspaces\WorkspaceRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a workspace.
 *
 * @internal
 */
class WorkspaceDeleteForm extends ContentEntityDeleteForm {

  /**
   * The workspace entity.
   *
   * @var \Drupal\workspaces\WorkspaceInterface
   */
  protected $entity;

  /**
   * The workspace tracker service.
   *
   * @var \Drupal\workspaces\WorkspaceTrackerInterface
   */
  protected $workspaceTracker;

  /**
   * The workspace repository service.
   *
   * @var \Drupal\workspaces\WorkspaceRepositoryInterface
   */
  protected $workspaceRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('workspaces.tracker'),
      $container->get('workspaces.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

  public function __construct(EntityRepositoryInterface $entity_repository, WorkspaceTrackerInterface $workspace_tracker, WorkspaceRepositoryInterface $workspace_repository, ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, ?TimeInterface $time = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->workspaceTracker = $workspace_tracker;
    $this->workspaceRepository = $workspace_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $workspace_tree = $this->workspaceRepository->loadTree();
    if (!empty($workspace_tree[$this->entity->id()]['descendants'])) {
      $form['description']['#markup'] = $this->t('The %label workspace can not be deleted because it has child workspaces.', [
        '%label' => $this->entity->label(),
      ]);
      $form['actions']['submit']['#disabled'] = TRUE;

      return $form;
    }

    $tracked_entities = $this->workspaceTracker->getTrackedEntities($this->entity->id());
    $items = [];
    foreach ($tracked_entities as $entity_type_id => $entity_ids) {
      $revision_ids = $this->workspaceTracker->getAllTrackedRevisions($this->entity->id(), $entity_type_id, $entity_ids);
      $label = $this->entityTypeManager->getDefinition($entity_type_id)->getLabel();
      $items[] = $this->formatPlural(count($revision_ids), '1 @label revision.', '@count @label revisions.', ['@label' => $label]);
    }
    $form['revisions'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('The following will also be deleted:'),
      '#items' => $items,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone, and will also delete all content created in this workspace.');
  }

}
