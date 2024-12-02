<?php

namespace Drupal\media\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides specific access control for the media entity type.
 */
#[EntityReferenceSelection(
  id: "default:media",
  label: new TranslatableMarkup("Media selection"),
  entity_types: ["media"],
  group: "default",
  weight: 1
)]
class MediaSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    // Ensure that users with insufficient permission cannot see unpublished
    // entities. In this instance the permission 'view any unpublished content'
    // applies to all moderated content including media.
    // @see https://www.drupal.org/project/drupal/i/3480675
    if (!$this->currentUser->hasPermission('administer media') && !$this->currentUser->hasPermission('view any unpublished content')) {
      // Permission to "view own unpublished media" allows
      // the user to reference any published media or own unpublished media.
      if ($this->currentUser->hasPermission('view own unpublished media')) {
        $or = $query->orConditionGroup()
          ->condition('status', 1)
          ->condition('uid', $this->currentUser->id());
        $query->condition($or);
      }
      else {
        $query->condition('status', 1);
      }
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function createNewEntity($entity_type_id, $bundle, $label, $uid) {
    $media = parent::createNewEntity($entity_type_id, $bundle, $label, $uid);

    // In order to create a referenceable media, it needs to published.
    /** @var \Drupal\media\MediaInterface $media */
    $media->setPublished();

    return $media;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities) {
    $entities = parent::validateReferenceableNewEntities($entities);
    // Mirror the conditions checked in buildEntityQuery().
    if (!$this->currentUser->hasPermission('administer media') && !$this->currentUser->hasPermission('view any unpublished content')) {
      $uid = $this->currentUser->id();
      $unpublished_permission = $this->currentUser->hasPermission('view own unpublished media');
      $entities = array_filter($entities, function ($media) use ($unpublished_permission, $uid) {
        $unpublished_access = ($unpublished_permission && ($media->getOwnerId() == $uid));
        /** @var \Drupal\media\MediaInterface $media */
        return ($unpublished_access || $media->isPublished());
      });
    }
    return $entities;
  }

}
