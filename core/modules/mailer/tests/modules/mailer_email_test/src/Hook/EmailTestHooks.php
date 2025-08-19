<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Mailer\EmailControllerParams;
use Drupal\Core\Mailer\EmailDefinition;
use Drupal\Core\State\StateInterface;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Email;

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

  #[Hook('email_params_alter')]
  public function emailParamsAlter(EmailControllerParams $params): void {
    foreach ($this->state->get('email_params_alter.set_attributes', []) as $key => $value) {
      $params->setAttribute($key, $value);
    }

    if (($controller = $this->state->get('email_params_alter.set_controller', NULL))) {
      $params->setControllerDefinition($controller);
    }

    if (($langcode = $this->state->get('email_params_alter.set_langcode', NULL))) {
      $params->environment['langcode'] = $langcode;
    }
  }

  #[Hook('email_build_alter')]
  public function emailBuildAlter(array &$build, array $context): void {
    foreach ($this->state->get('email_build_alter.set_elements', []) as $key => $element) {
      $build[$key] = $element;
    }
  }

  #[Hook('email_alter')]
  public function emailAlter(Email $email, array $context): void {
    foreach ($this->state->get('email_alter.set_tags', []) as $tag) {
      $email->getHeaders()->add(new TagHeader($tag));
    }
    foreach ($this->state->get('email_alter.set_metadata', []) as $key => $value) {
      $email->getHeaders()->add(new MetadataHeader($key, $value));
    }
    if ($this->state->get('email_alter.capture_render_metadata')) {
      $this->state->set('email_alter.render_metadata', $context['metadata']);
    }
  }

}
