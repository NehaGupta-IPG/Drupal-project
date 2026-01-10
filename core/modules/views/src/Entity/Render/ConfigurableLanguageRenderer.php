<?php

namespace Drupal\views\Entity\Render;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Renders entities in a configured language.
 */
class ConfigurableLanguageRenderer extends EntityTranslationRendererBase {

  /**
   * A specific language code for rendering if available.
   *
   * @var string|null
   */
  protected $langcode;

  public function __construct(ViewExecutable $view, LanguageManagerInterface $language_manager, EntityTypeInterface $entity_type, $langcode) {
    parent::__construct($view, $language_manager, $entity_type);
    $this->langcode = $langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(ResultRow $row) {
    return $this->langcode;
  }

}
