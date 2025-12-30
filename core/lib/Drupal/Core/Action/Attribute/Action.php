<?php

namespace Drupal\Core\Action\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an Action attribute object.
 *
 * Plugin Namespace: Plugin\Action
 *
 * @see \Drupal\Core\Action\ActionInterface
 * @see \Drupal\Core\Action\ActionManager
 * @see \Drupal\Core\Action\ActionBase
 * @see \Drupal\Core\Action\Plugin\Action\UnpublishAction
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Action extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $action_label = NULL,
    public readonly ?TranslatableMarkup $category = NULL,
    public readonly ?string $deriver = NULL,
    public readonly ?string $confirm_form_route_name = NULL,
    public readonly ?string $type = NULL,
  ) {}

}
