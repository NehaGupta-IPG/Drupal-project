<?php

namespace Drupal\Core\Mailer\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\Mailer\EmailPluginDefault;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Email attribute object.
 *
 * Plugin Namespace: Plugin\Email
 *
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Email extends Plugin {

  /**
   * Constructs a Email attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the email.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A short description of the email.
   * @param class-string $class
   *   (optional) The email plugin class.
   * @param string|null $html_body_template
   *   (optional) The name of a template which is used to render the HTML body.
   * @param string|null $text_body_template
   *   (optional) The name of a template which is used to render the text body.
   * @param string|null $default_subject
   *   (optional) The subject which is used by default for this email.
   * @param array|null $default_recipients
   *   (optional) Recipients to be added to the mail by default. Takes a list
   *   with the following keys: to, cc, bcc, each with a list of strings as the
   *   value.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    protected string $class = EmailPluginDefault::class,
    public readonly ?string $html_body_template = NULL,
    public readonly ?string $text_body_template = NULL,
    public readonly ?string $default_subject = NULL,
    public readonly ?array $default_recipients = NULL,
  ) {

  }

}
