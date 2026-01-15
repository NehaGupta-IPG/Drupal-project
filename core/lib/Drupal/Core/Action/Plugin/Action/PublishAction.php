<?php

namespace Drupal\Core\Action\Plugin\Action;

use Drupal\Core\Action\ActionResult;
use Drupal\Core\Action\Plugin\Action\Derivative\EntityPublishedActionDeriver;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Publishes an entity.
 */
#[Action(
  id: 'entity:publish_action',
  action_label: new TranslatableMarkup('Publish'),
  deriver: EntityPublishedActionDeriver::class
)]
class PublishAction extends EntityActionBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(EntityInterface $entity): ActionResult {
    $entity->setPublished()->save();
    $entity->getEntityType()->getLabel();
    return ActionResult::success($this->t('The @type %label has been published.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]));
  }

  /**
   * {@inheritdoc}
   */
  public function access(?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $entity = $this->getContextValue($this->getDerivativeId());
    $key = $entity->getEntityType()->getKey('published');

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $result = $entity->access('update', $account, TRUE)
      ->andIf($entity->$key->access('edit', $account, TRUE));

    return $return_as_object ? $result : $result->isAllowed();
  }

}
