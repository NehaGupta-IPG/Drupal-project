<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Mailer\EmailDefinition;
use Drupal\Core\State\StateInterface;

/**
 * Test email controller methods.
 */
class EmailTestHooks {

  public function __construct(
    protected readonly StateInterface $state,
  ) {
  }

  #[Hook('email_info_alter')]
  public function emailInfoAlter(array &$definitions): void {
    if ($this->state->get('email_info_alter.remove_controller', FALSE)) {
      $values = $definitions['mailer_email_test.scalars']->toArray();
      $defaults = $values['defaults'];
      unset($defaults[EmailDefinition::CONTROLLER_NAME]);
      $definitions['mailer_email_test.scalars'] = new EmailDefinition([
        ...$values,
        'defaults' => $defaults,
      ]);
    }
  }

}
