<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Action\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ConfigAction attribute object.
 *
 * Plugin Namespace: Plugin\ConfigAction
 *
 * @ingroup config_action_api
 *
 * @internal
 *   This API is experimental.
 *
 * @see \Drupal\Core\Config\Action\ConfigActionPluginInterface
 * @see \Drupal\Core\Config\Action\ConfigActionManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ConfigAction extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $admin_label = NULL,
    public readonly array $entity_types = [],
    public readonly ?string $deriver = NULL,
  ) {
    if ($this->admin_label === NULL && $this->deriver === NULL) {
      throw new InvalidPluginDefinitionException($id, sprintf("The '%s' config action plugin must have either an admin label or a deriver", $id));
    }
  }

}
