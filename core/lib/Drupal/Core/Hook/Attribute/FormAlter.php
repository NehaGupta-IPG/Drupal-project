<?php

declare(strict_types=1);

namespace Drupal\Core\Hook\Attribute;

use Drupal\Core\Hook\Order\OrderInterface;

/**
 * Attribute for altering a form by its class name (FQCN).
 *
 * This provides an alternative to using Hook('form_FORM_ID_alter') by
 * allowing developers to reference the form class directly.
 *
 * Example usage:
 *
 * @code
 * use Drupal\system\Form\LoggingForm;
 *
 * #[FormAlter(LoggingForm::class)]
 * public function alterLoggingForm(&$form, FormStateInterface $form_state): void {
 *   // Alter the form.
 * }
 * @endcode
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class FormAlter extends Hook {

  /**
   * Constructs a FormAlter attribute object.
   *
   * @param string $formClass
   *   The fully qualified class name of the form to alter.
   * @param string $method
   *   (optional) The method name. If this attribute is on a method, this
   *   parameter is not required. If this attribute is on a class and this
   *   parameter is omitted, the class must have an __invoke() method, which is
   *   taken as the hook implementation.
   * @param string|null $module
   *   (optional) The module this implementation is for. This allows one module
   *   to implement a hook on behalf of another module. Defaults to the module
   *   the implementation is in.
   * @param \Drupal\Core\Hook\Order\OrderInterface|null $order
   *   (optional) Set the order of the implementation.
   */
  public function __construct(
    string $formClass,
    string $method = '',
    ?string $module = NULL,
    ?OrderInterface $order = NULL,
  ) {
    parent::__construct('form_' . $formClass . '_alter', $method, $module, $order);
  }

}
