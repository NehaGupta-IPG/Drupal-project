<?php

namespace Drupal\views\Plugin\Derivative;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableEntityLabelMarkup;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides translatable markup as admin label for view displayed as block.
 */
class TranslatableViewsBlockAdminLabelMarkup extends TranslatableEntityLabelMarkup {

  /**
   * The id of the view display.
   *
   * @var string
   */
  protected $displayId;

  /**
   * TranslatableViewsBlockAdminLabelMarkup constructor.
   *
   * @param string $view_id
   *   The id of the view entity.
   * @param string $display_id
   *   The id of the view display.
   * @param string|null $langcode
   *   The language the entity label should be translated to, or the current
   *   interface language if NULL.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface|null $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct($view_id, $display_id, $langcode = NULL, EntityTypeManagerInterface $entity_type_manager = NULL, EntityRepositoryInterface $entity_repository = NULL, LanguageManagerInterface $language_manager = NULL) {
    parent::__construct('view', $view_id, $langcode, $entity_type_manager, $entity_repository, $language_manager);
    $this->displayId = $display_id;
  }

  /**
   * Magic __sleep() method to avoid serializing services.
   */
  public function __sleep() {
    $sleep_properties = parent::__sleep();
    $sleep_properties[] = 'displayId';
    return $sleep_properties;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTranslatedLabel(EntityInterface $entity) {
    /** @var \Drupal\views\ViewEntityInterface $view */
    $view = $entity;
    $executable = $view->getExecutable();
    // Set the view display to the specified display id, if it exists.
    if ($executable->setDisplay($this->displayId)) {
      $display = $executable->display_handler;
      // Get label from the set display configuration.
      $label = Html::escape($display->getOption('block_description'));
      if (empty($label)) {
        if ($display->display['display_title'] == $display->definition['title']) {
          $label = Html::escape($view->label());
        }
        else {
          // Allow translators to control the punctuation.
          $label = new TranslatableMarkup('@view: @display', [
            '@view' => $view->label(),
            '@display' => $display->display['display_title'],
          ], [
            'langcode' => $this->langcode,
          ]);
        }
      }
    }
    else {
      $label = new TranslatableMarkup('View %view_id does not have display %display_id.', [
        '%view_id' => $view->id(),
        '%display_id' => $this->displayId,
      ], [
        'langcode' => $this->langcode,
      ]);
    }

    if ($label instanceof TranslatableMarkup) {
      $label = (string) $label;
    }
    return $label;
  }

}
