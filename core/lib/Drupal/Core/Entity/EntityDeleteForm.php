<?php

namespace Drupal\Core\Entity;

use Drupal\Core\Config\Entity\ConfigDependencyDeleteFormTrait;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a generic base class for an entity deletion form.
 *
 * @ingroup entity_api
 */
class EntityDeleteForm extends EntityConfirmFormBase {

  use ConfigDependencyDeleteFormTrait;

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return $this->entity->getEntityTypeId() . '_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $entity = $this->getEntity();
    // Only do dependency processing for configuration entities. Whilst it is
    // possible for a configuration entity to be dependent on a content entity,
    // these dependencies are soft and content delete permissions are often
    // given to more users. This method should not make assumptions that $entity
    // is a configuration entity in case we decide to remove the following
    // condition.
    if (!($entity instanceof ConfigEntityInterface)) {
      return $form;
    }
    $this->addDependencyListsToForm($form, $entity->getConfigDependencyKey(), $this->getConfigNamesToDelete($entity), $this->getConfigManager(), $this->entityTypeManager);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->getEntity()->delete();
    $this->messenger()->addMessage($this->getDeletionMessage());
    $form_state->setRedirectUrl($this->getCancelUrl());
    $this->logDeletionMessage();
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the @entity-type %label?', [
      '@entity-type' => $this->getEntity()->getEntityType()->getLowercaseLabel(),
      '%label' => $this->getEntity()->label(),
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
      '@entity-type' => $entity->getEntityType()->getLowercaseLabel(),
      '%label' => $entity->label(),
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
    $this->logger($entity->getEntityType()->getProvider())->notice('The @entity-type %label has been deleted.', [
      '@entity-type' => $entity->getEntityType()->getLowercaseLabel(),
      '%label' => $entity->label(),
    ]);
  }

  /**
   * Gets the configuration manager.
   *
   * @return \Drupal\Core\Config\ConfigManager
   *   The configuration manager.
   */
  protected function getConfigManager() {
    return \Drupal::service('config.manager');
  }

  /**
   * Returns config names to delete for the deletion confirmation form.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity being deleted.
   *
   * @return string[]
   *   A list of configuration names that will be deleted by this form.
   */
  protected function getConfigNamesToDelete(ConfigEntityInterface $entity) {
    return [$entity->getConfigDependencyName()];
  }

}
