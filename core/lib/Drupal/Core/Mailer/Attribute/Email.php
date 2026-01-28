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
   * @param string $id
   * @param TranslatableMarkup|null $label
   * @param TranslatableMarkup|null $description
   * @param string|null $module
   * @param string|null $key
   * @param string $class
   * @param string|null $html_body_template
   * @param string|null $text_body_template
   * @param string|null $default_subject
   * @param array|null $default_recipients
   */
  public function __construct(
    public readonly string              $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string              $module = '',
    public readonly ?string              $key = '',
    public string                        $class = EmailPluginDefault::class,
    public readonly ?string              $html_body_template = NULL,
    public readonly ?string              $text_body_template = NULL,
    public readonly ?string              $default_subject = NULL,
    public readonly ?array               $default_recipients = NULL,
  )
  {

  }

}
