<?php

namespace Drupal\views\Plugin\views\argument_validator;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsArgumentValidator;

/**
 * Provide a basic argument validation.
 *
 * This can be overridden for more complex types; the basic validator only
 * checks to see if the argument is not NULL or is numeric if the definition
 * says it's numeric.
 *
 * @return bool
 *   TRUE is the argument validates, FALSE otherwise.
 *
 * @ingroup views_argument_validate_plugins
 */
#[ViewsArgumentValidator(
  id: 'none',
  title: new TranslatableMarkup('- Basic validation -')
)]
class None extends ArgumentValidatorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function validateArgument($argument) {
    if (!empty($this->argument->options['must_not_be'])) {
      return !isset($argument);
    }

    if (!isset($argument) || $argument === '') {
      return FALSE;
    }

    if (($this->argument->getPluginId() === 'numeric')
      && empty($this->argument->options['break_phrase'])
      && !is_numeric($argument)) {
      return FALSE;
    }

    return TRUE;
  }

}
