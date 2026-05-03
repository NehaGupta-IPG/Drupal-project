<?php

declare(strict_types=1);

namespace Drupal\system\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\system\DateFormatListBuilder;
use Drupal\system\Form\DateFormatAddForm;
use Drupal\system\Form\DateFormatDeleteForm;
use Drupal\system\Form\DateFormatEditForm;

/**
 * Entity hook implementations for system.
 */
class SystemEntityHooks {

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    $entity_types['date_format']
      ->setFormClass('add', DateFormatAddForm::class)
      ->setFormClass('edit', DateFormatEditForm::class)
      ->setFormClass('delete', DateFormatDeleteForm::class)
      ->setListBuilderClass(DateFormatListBuilder::class)
      ->setLinkTemplate('edit-form', '/admin/config/regional/date-time/formats/manage/{date_format}')
      ->setLinkTemplate('delete-form', '/admin/config/regional/date-time/formats/manage/{date_format}/delete')
      ->setLinkTemplate('collection', '/admin/config/regional/date-time/formats');
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for entity_form_mode entities.
   *
   * Transforms empty description into null.
   */
  #[Hook('hook_entity_form_mode_presave')]
  public function systemEntityFormModePresave(EntityInterface $entity): void {
    if ($entity->get('description') !== NULL && trim($entity->get('description')) === '') {
      @trigger_error("Setting description to an empty string is deprecated in drupal:11.2.0 and it must be null in drupal:12.0.0. See https://www.drupal.org/node/3452144", E_USER_DEPRECATED);
      $entity->set('description', NULL);
    }
  }

}
