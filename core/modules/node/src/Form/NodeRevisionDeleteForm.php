<?php

namespace Drupal\node\Form;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Provides a form for deleting a node revision.
 *
 * @internal
 */
class NodeRevisionDeleteForm extends ConfirmFormBase {

  /**
   * The node revision.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $revision;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * The node type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeTypeStorage;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    protected AccessManagerInterface $accessManager,
    protected DateFormatterInterface $dateFormatter,
  ) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->nodeTypeStorage = $entity_type_manager->getStorage('node_type');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the revision from %revision-date?', [
      '%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.node.version_history', ['node' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node_revision = NULL) {
    $this->revision = $node_revision;
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->nodeStorage;
    $storage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')
      ->info('@type: deleted %title revision %revision.', [
        '@type' => $this->revision->bundle(),
        '%title' => $this->revision->label(),
        '%revision' => $this->revision->getRevisionId(),
      ]);
    $node_type = $this->nodeTypeStorage->load($this->revision->bundle())->label();
    $this->messenger()
      ->addStatus($this->t('Revision from %revision-date of @type %title has been deleted.', [
        '%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
        '@type' => $node_type,
        '%title' => $this->revision->label(),
      ]));
    // Set redirect to the revisions history page.
    $route_name = 'entity.node.version_history';
    $parameters = ['node' => $this->revision->id()];
    // If no revisions found, or the user does not have access to the revisions
    // page, then redirect to the canonical node page instead.
    $revision_count_query = $storage->getQuery()->allRevisions()->condition('nid', $this->revision->id())->accessCheck(FALSE)->count();
    if (!$this->accessManager->checkNamedRoute($route_name, $parameters) || $revision_count_query->execute() === 1) {
      $route_name = 'entity.node.canonical';
    }
    $form_state->setRedirect($route_name, $parameters);
  }

}
