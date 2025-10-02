<?php

declare(strict_types=1);

namespace Drupal\entity_test\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\ChangedFieldItemList;
use Drupal\Core\Field\Plugin\Field\FieldType\ChangedItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the 'changed_test' entity field type.
 *
 * Wraps Drupal\Core\Field\Plugin\Field\FieldType\ChangedItem.
 *
 * @see \Drupal\Core\Entity\EntityChangedInterface
 *
 * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use
 * \Drupal\Mocks\TestMockedTime or \Drupal\update_test\Datetime\TestTime
 * instead.
 * @see https://www.drupal.org/node/3549944
 */
#[FieldType(
  id: "changed_test",
  label: new TranslatableMarkup("Last changed"),
  description: new TranslatableMarkup("An entity field containing a UNIX timestamp of when the entity has been last updated."),
  no_ui: TRUE,
  list_class: ChangedFieldItemList::class,
)]
class ChangedTestItem extends ChangedItem {

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();

    @trigger_error('The changed_test field type is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use \Drupal\Mocks\TestMockedTime or \Drupal\update_test\Datetime\TestTime instead. See https://www.drupal.org/node/3549944', E_USER_DEPRECATED);

    if ($this->value == \Drupal::time()->getRequestTime()) {
      // During a test the request time is immutable. To allow tests of the
      // algorithm of
      // Drupal\Core\Field\Plugin\Field\FieldType\ChangedItem::preSave() we need
      // to set a real time value here. For the stability of the test, set the
      // time of the original language to the current time plus just over one
      // second to simulate two different request times.
      if ($this->getEntity()->language()->isDefault()) {
        // Wait 1.1 seconds because time_sleep_until() is not reliable.
        time_sleep_until(time() + 1.1);
      }
      $this->value = time();
    }
  }

}
