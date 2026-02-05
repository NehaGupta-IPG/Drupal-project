<?php

namespace Drupal\content_translation;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for providing content translation.
 *
 * Defines a set of methods to allow any entity to be processed by the entity
 * translation UI.
 */
interface ContentTranslationHandlerInterface {

  /**
   * Returns a set of field definitions to be used to store metadata items.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The field definitions.
   */
  public function getFieldDefinitions();

  /**
   * Checks that the user can perform the operation on the entity translation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose translation has to be accessed.
   * @param string $op
   *   The operation to be performed on the translation. Possible values are:
   *   - "create".
   *   - "update".
   *   - "delete".
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function getTranslationAccess(EntityInterface $entity, $op);

  /**
   * Retrieves the source language for the translation being created.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return string
   *   The source language code.
   */
  public function getSourceLangcode(FormStateInterface $form_state);

  /**
   * Marks translations as outdated.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being translated.
   * @param string $langcode
   *   (optional) The language code of the updated language: all the other
   *   translations will be marked as outdated. Defaults to the entity language.
   */
  public function retranslate(EntityInterface $entity, $langcode = NULL);

  /**
   * Update the outdated status for the last affected revision translation.
   *
   * For moderated entities, the content_translation_outdated flag is
   * determined by the latest revision where revision_translation_affected
   * is TRUE. Therefore, to correctly keep the content_translation_outdated
   * flag up to date, the old latest affected revision translation must be
   * marked as up to date before the next affected revision translation is
   * created.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity currently being edited.
   */
  public function resetLastRevisionOutdatedStatus(EntityInterface $entity): void;

  /**
   * Performs the needed alterations to the entity form.
   *
   * @param array $form
   *   The entity form to be altered to provide the translation workflow.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being created or edited.
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity);

}
