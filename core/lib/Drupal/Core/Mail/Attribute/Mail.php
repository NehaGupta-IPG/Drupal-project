<?php

declare(strict_types=1);

namespace Drupal\Core\Mail\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Mail attribute for plugin discovery.
 *
 * Plugin Namespace: Plugin\Mail
 *
 * For a working example, see \Drupal\Core\Mail\Plugin\Mail\PhpMail
 *
 * @see \Drupal\Core\Mail\MailInterface
 * @see \Drupal\Core\Mail\MailManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Mail extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
