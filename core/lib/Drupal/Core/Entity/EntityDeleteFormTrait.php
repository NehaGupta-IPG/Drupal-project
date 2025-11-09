<?php

namespace Drupal\Core\Entity;

@trigger_error('The ' . __NAMESPACE__ . '\EntityDeleteFormTrait is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Instead, use \Drupal\Core\Entity\EntityDeleteForm. See https://www.drupal.org/node/2491057', E_USER_DEPRECATED);

use Drupal\Core\Config\Entity\ConfigDependencyDeleteFormTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a trait for an entity deletion form.
 *
 * This trait relies on the StringTranslationTrait and the logger method added
 * by FormBase.
 *
 * @deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use the
 *   \Drupal\Core\Entity\EntityDeleteForm instead.
 *
 * @see https://www.drupal.org/node/2926275
 *
 * @internal
 *
 * @ingroup entity_api
 */
trait EntityDeleteFormTrait {
  use ConfigDependencyDeleteFormTrait;

  /**
   * Gets the entity of this form.
   *
   * Provided by \Drupal\Core\Entity\EntityForm.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  abstract public function getEntity();

  /**
   * Gets the logger for a specific channel.
   *
   * Provided by \Drupal\Core\Form\FormBase.
   *
   * @param string $channel
   *   The name of the channel.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger for this channel.
   */
  abstract protected function logger($channel);

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the @entity-type %label?', [
      '@entity-type' => $this->getEntity()->getEntityType()->getSingularLabel(),
      '%label' => $this->getEntity()->label() ?? $this->getEntity()->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * Gets the message to display to the user after deleting the entity.
   *
   * @return string
   *   The translated string of the deletion message.
   */
  protected function getDeletionMessage() {
    $entity = $this->getEntity();
    return $this->t('The @entity-type %label has been deleted.', [
      '@entity-type' => $entity->getEntityType()->getSingularLabel(),
      '%label' => $entity->label() ?? $entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $entity = $this->getEntity();
    if ($entity->hasLinkTemplate('collection')) {
      // If available, return the collection URL.
      return $entity->toUrl('collection');
    }
    else {
      // Otherwise fall back to the default link template.
      return $entity->toUrl();
    }
  }

  /**
   * Returns the URL where the user should be redirected after deletion.
   *
   * @return \Drupal\Core\Url
   *   The redirect URL.
   */
  protected function getRedirectUrl() {
    $entity = $this->getEntity();
    if ($entity->hasLinkTemplate('collection')) {
      // If available, return the collection URL.
      return $entity->toUrl('collection');
    }
    else {
      // Otherwise fall back to the front page.
      return Url::fromRoute('<front>');
    }
  }

  /**
   * Logs a message about the deleted entity.
   */
  protected function logDeletionMessage() {
    $entity = $this->getEntity();
    $this->logger($entity->getEntityType()->getProvider())->info('The @entity-type %label has been deleted.', [
      '@entity-type' => $entity->getEntityType()->getSingularLabel(),
      '%label' => $entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->getEntity()->delete();
    $this->messenger()->addStatus($this->getDeletionMessage());
    $form_state->setRedirectUrl($this->getCancelUrl());
    $this->logDeletionMessage();
  }

}
