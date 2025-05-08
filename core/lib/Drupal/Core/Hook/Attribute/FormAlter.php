<?php

declare(strict_types=1);

namespace Drupal\Core\Hook\Attribute;

use Drupal\Core\Hook\Order\OrderInterface;

/**
 * Hook attribute for FormAlter.
 *
 * @see hook_form_alter()
 * @see hook_form_BASE_FORM_ID_alter()
 * @see hook_form_FORM_ID_alter()
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class FormAlter extends Hook {

  /**
   * Constructs a FormAlter attribute object.
   *
   * @param string|null $form_id
   *   (optional) The ID of the target form, or NULL to target all forms.
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
    ?string $form_id = NULL,
    string $method = '',
    ?string $module = NULL,
    ?OrderInterface $order = NULL,
  ) {
    $hook = ($form_id === NULL) ? 'form_alter' : 'form_' . $form_id . '_alter';
    parent::__construct($hook, $method, $module, $order);
  }

}
