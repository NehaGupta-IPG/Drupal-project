<?php

namespace Drupal\node\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;

/**
 * Provides specific access control for the node entity type.
 */
#[EntityReferenceSelection(
  id: "default:node",
  label: new TranslatableMarkup("Node selection"),
  entity_types: ["node"],
  group: "default",
  weight: 1
)]
class NodeSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   *
   * @todo Move this to DefaultSelection in https://www.drupal.org/node/3110677
   *   so that every entity type implementing EntityPublishedInterface can
   *   reference its unpublished entities.
   */
  public function defaultConfiguration() {
    return [
      'include_unpublished_entities' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['include_unpublished_entities'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include unpublished nodes the user has access to view'),
      '#description' => $this->t('Unpublished nodes are only matched for users who are allowed to view them.'),
      '#default_value' => $this->getConfiguration()['include_unpublished_entities'],
      '#weight' => 0,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    // Adding the 'node_access' tag is sadly insufficient for nodes: core
    // requires us to also know about the concept of 'published' and
    // 'unpublished'. We need to do that as long as there are no access control
    // modules in use on the site. As long as one access control module is
    // there, it is supposed to handle this check.
    if ($this->currentUser->hasPermission('bypass node access') || $this->moduleHandler->hasImplementations('node_grants')) {
      return $query;
    }

    // The "view own unpublished content" permission is never granted to
    // anonymous users.
    /* @see \Drupal\node\NodeAccessControlHandler::checkAccess() */
    $view_own = $this->currentUser->isAuthenticated() && $this->currentUser->hasPermission('view own unpublished content');
    $view_any = $this->currentUser->hasPermission('view any unpublished content');

    // Unpublished nodes are only referenceable when the field asks for them
    // and the user may view them.
    $view_unpublished = $this->getConfiguration()['include_unpublished_entities'] && ($view_own || $view_any);

    if (!$view_unpublished) {
      $query->condition('status', NodeInterface::PUBLISHED);
    }
    elseif (!$view_any) {
      // Only the user's own unpublished nodes are referenceable, the rest of
      // them needs the "view any unpublished content" permission.
      $query->condition($query->orConditionGroup()
        ->condition('status', NodeInterface::PUBLISHED)
        ->condition('uid', $this->currentUser->id()));
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function createNewEntity($entity_type_id, $bundle, $label, $uid) {
    $node = parent::createNewEntity($entity_type_id, $bundle, $label, $uid);

    // In order to create a referenceable node, it needs to published.
    /** @var \Drupal\node\NodeInterface $node */
    $node->setPublished();

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities) {
    $entities = parent::validateReferenceableNewEntities($entities);
    // Mirror the conditions checked in buildEntityQuery().
    if (!$this->currentUser->hasPermission('bypass node access') && !$this->moduleHandler->hasImplementations('node_grants')) {
      $entities = array_filter($entities, function ($node) {
        /** @var \Drupal\node\NodeInterface $node */
        return $node->isPublished();
      });
    }
    return $entities;
  }

}
