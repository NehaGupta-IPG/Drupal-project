<?php

namespace Drupal\taxonomy\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Url;

/**
 * Provides a deletion confirmation form for taxonomy term.
 *
 * @internal
 */
class TermDeleteForm extends ContentEntityDeleteForm {

  /**
   * The list of all terms to be deleted if this form action is confirmed.
   *
   * This variable will be undefined until getDeletedTerms() is called.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */

  private $deletedTerms;

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    // The cancel URL is the vocabulary collection, terms have no global
    // list page.
    return new Url('entity.taxonomy_vocabulary.collection');
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    return $this->getCancelUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $term = $this->getEntity();
    $descendants = $this->getDeletedTerms();
    unset($descendants[$term->id()]);
    if ($descendants) {
      $descendants_last = end($descendants);
      $descendants_head = array_slice($descendants, 0, -1);
      return $this->formatPlural(count($descendants),
        'Deleting @entity-type %label will also delete its descendant %descendant. This action cannot be undone.',
        'Deleting @entity-type %label will also delete its descendants @descendants and %descendant. This action cannot be undone.',
        [
          '@entity-type' => $term->getEntityType()->getSingularLabel(),
          '%label' => $term->label(),
          '%descendant' => $descendants_last->label(),
          '@descendants' => new FormattableMarkup(
            '%' . implode(', %', array_keys($descendants_head)),
            array_combine(
              array_map(function ($id) {
                return "%$id";
              }, array_keys($descendants_head)),
              array_map(function ($term) {
                return $term->label();
              }, $descendants_head)
            )
          ),
        ]
      );
    }
    else {
      return $this->t('Deleting @entity-type %label cannot be undone.', [
        '@entity-type' => $term->getEntityType()->getSingularLabel(),
        '%label' => $term->label(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    $entity = $this->getEntity();
    $deleted = $this->getDeletedTerms();
    $deleted_last = end($deleted);
    $deleted_head = array_slice($deleted, 0, -1);
    return $this->formatPlural(count($deleted),
      'The @entity-type %label has been deleted.',
      'The @entity-type-plural @labels and %label have been deleted.',
      [
        '@entity-type' => $entity->getEntityType()->getSingularLabel(),
        '@entity-type-plural' => $this->t('taxonomy terms'),
        '%label' => $deleted_last->label(),
        '@labels' => new FormattableMarkup(
          '%' . implode(', %', array_keys($deleted_head)),
          array_combine(
            array_map(function ($id) {
              return "%$id";
            }, array_keys($deleted_head)),
            array_map(function ($term) {
              return $term->label();
            }, $deleted_head)
          )
        ),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function logDeletionMessage() {
    $entity = $this->getEntity();
    $deleted = $this->getDeletedTerms();
    if (count($deleted) > 1) {
      $deleted_last = end($deleted);
      $deleted_head = array_slice($deleted, 0, -1);
      $this->logger($entity->getEntityType()->getProvider())->notice('The @entity-type @labels and %label have been deleted.', [
        '@entity-type' => 'taxonomy terms',
        '%label' => $deleted_last->label(),
        '@labels' => new FormattableMarkup(
          '%' . implode(', %', array_keys($deleted_head)),
          array_combine(
            array_map(function ($id) {
              return "%$id";
            }, array_keys($deleted_head)),
            array_map(function ($term) {
              return $term->label();
            }, $deleted_head)
          )
        ),
      ]);
    }
    else {
      $this->logger($entity->getEntityType()->getProvider())->notice('The @entity-type %label has been deleted.', [
        '@entity-type' => $entity->getEntityType()->getSingularLabel(),
        '%label' => $entity->label(),
      ]);
    }
  }

  /**
   * Finds all terms to be will deleted should this form action be confirmed.
   *
   * When a taxonomy term is deleted, all its descendants without a non-deleted
   * ancestor are also deleted.
   *
   * Once retrieved, the list of deleted terms is cached. This can be used to
   * ensure the list of deleted terms is usable after they have been actually
   * deleted.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   An array of term objects, indexed by id.
   */
  public function getDeletedTerms() {
    if (!isset($this->deletedTerms)) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $term */
      $term = $this->getEntity();
      $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
      $this->deletedTerms = [$term->id() => $term];
      // Get all the terms below the form's term,
      // indexed by id and ordered by depth.
      $children = $termStorage->loadTree($term->bundle(), $term->id(), NULL, TRUE);
      $children = array_combine(array_map(function ($term) {
        return $term->id();

      }, $children), $children);
      uasort($children, function ($a, $b) {
        if ($a->depth = $b->depth) {
          return 0;
        }
        return ($a->depth < $b->depth) ? -1 : 1;
      });

      // Find all future orphans.
      foreach ($children as $child) {
        // An term is a future orphan if all its parent are also deleted.
        if (empty(array_diff_key($termStorage->loadParents($child->id()), $this->deletedTerms))) {
          $this->deletedTerms[$child->id()] = $child;
        }
      }
    }
    return $this->deletedTerms;
  }

}
