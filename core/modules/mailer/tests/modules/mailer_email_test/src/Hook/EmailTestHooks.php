<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Mailer\EmailPluginInterface;
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

  #[Hook('email_pre_build_alter')]
  public function emailPreBuildAlter(EmailPluginInterface $plugin): void {
    foreach ($this->state->get('email_pre_build_alter.set_params', []) as $key => $value) {
      $plugin->setParameter($key, $value);
    }

    if (($langcode = $this->state->get('email_pre_build_alter.set_langcode', NULL))) {
      $plugin->setEnvironmentParameter('langcode', $langcode);
    }
  }

  #[Hook('email_pre_render_alter')]
  public function emailPreRenderAlter(array &$build, array $context): void {
    foreach ($this->state->get('email_pre_render_alter.set_elements', []) as $key => $element) {
      $build['body']['html'][$key] = $element;
    }
  }

  #[Hook('email_post_render_alter')]
  public function emailPostRenderAlter(Email $symfonyEmail, array $context): void {
    if ($this->state->get('email_post_render_alter.capture_assets')) {
      $this->state->set('email_post_render_alter.assets', $context['assets']);
    }
  }

  #[Hook('email_pre_send_alter')]
  public function emailPreSendAlter(EmailPluginInterface $plugin, Email $email): void {
    foreach ($this->state->get('email_pre_send_alter.set_tags', []) as $tag) {
      $email->getHeaders()->add(new TagHeader($tag));
    }
    foreach ($this->state->get('email_pre_send_alter.set_metadata', []) as $key => $value) {
      $email->getHeaders()->add(new MetadataHeader($key, $value));
    }
  }

}
