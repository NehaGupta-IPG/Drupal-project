<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Mailer\EmailParams;
use Drupal\Core\State\StateInterface;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Email;

/**
 * Test email plugin methods.
 */
class EmailTestHooks {

  public function __construct(
    protected readonly StateInterface $state,
  ) {
  }

  #[Hook('email_params_alter')]
  public function emailParamsAlter(EmailParams $params): void {
    foreach ($this->state->get('email_params_alter.set_attributes', []) as $key => $value) {
      $params->setAttribute($key, $value);
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
