<?php

namespace Drupal\views\Plugin\views\argument_validator;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsArgumentValidator;

/**
 * Validate whether an argument is numeric or not.
 *
 * @ingroup views_argument_validate_plugins
 */
#[ViewsArgumentValidator(
  id: 'numeric',
  title: new TranslatableMarkup('Numeric')
)]
class NumericArgumentValidator extends ArgumentValidatorPluginBase {

  /**
   * Performs validation for a given argument.
   *
   * @param mixed $argument
   *   The argument to validate.
   * phpcs:ignore Drupal.Commenting.FunctionComment.ParamNameNoMatch
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   (optional) Collects the cacheability of the validation result.
   *
   * @return bool
   *   TRUE if the argument is valid, FALSE otherwise.
   */
  public function validateArgument($argument /* , RefinableCacheableDependencyInterface $cacheability = new CacheableMetadata() */) {
    return is_numeric($argument);
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinition() {
    return new ContextDefinition('integer', $this->argument->adminLabel(), FALSE);
  }

}
