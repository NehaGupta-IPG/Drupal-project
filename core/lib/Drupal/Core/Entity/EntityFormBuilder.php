<?php

namespace Drupal\Core\Entity;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;

/**
 * Builds entity forms.
 */
class EntityFormBuilder implements EntityFormBuilderInterface {

  /**
   * The entity type manager service.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The form builder.
   *
   * @var FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new EntityFormBuilder.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(EntityInterface $entity, $operation = 'default', array $form_state_additions = []) {
    $form_object = $this->entityTypeManager->getFormObject($entity->getEntityTypeId(), $operation);
    $form_object->setEntity($entity);

    $form_state = (new FormState())->setFormState($form_state_additions);
    return $this->formBuilder->buildForm($form_object, $form_state);
  }

}
