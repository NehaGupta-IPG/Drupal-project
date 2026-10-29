<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\ConfigAction;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\Views;
use Symfony\Component\String\UnicodeString;

/**
 * Defines a deriver for the 'setDisplayOption' config action.
 *
 * For every handler type (field, filter, sort, ...) this creates an action
 * such as 'setFieldInDisplay' that takes the item name in a typed key such as
 * 'field_name'. For the single value options provided by Views core it creates
 * actions such as 'setPagerInDisplay'. The base 'setDisplayOption' action is
 * kept for everything else.
 */
final class SetDisplayOptionDeriver extends DeriverBase {
  use StringTranslationTrait;

  /**
   * Single value display options provided by Views core.
   *
   * @var string[]
   */
  private const SINGLE_VALUE_OPTIONS = [
    'access',
    'cache',
    'css_class',
    'exposed_form',
    'filter_groups',
    'group_by',
    'hide_attachment_summary',
    'link_display',
    'pager',
    'query',
    'row',
    'show_admin_links',
    'style',
    'title',
    'use_ajax',
    'use_more',
    'use_more_always',
    'use_more_text',
  ];

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // An empty derivative ID keeps the generic action with its original ID.
    $this->derivatives[''] = $base_plugin_definition;

    // The plural form of the generic action, as the payload may be a list.
    $this->derivatives['setDisplayOptions'] = $base_plugin_definition;

    foreach (Views::getHandlerTypes() as $type => $info) {
      $definition = [
        'admin_label' => $this->t('Set @type in a Views display', ['@type' => $info['lstitle']]),
        'option' => $info['plural'],
        'item_key' => $type . '_name',
      ] + $base_plugin_definition;
      $this->derivatives['set' . (new UnicodeString($type))->camel()->title()->toString() . 'InDisplay'] = $definition;
      // Plural alias, for example 'setFieldsInDisplay'. Some types have the same
      // singular and plural name, in which case the alias already exists.
      $plural_id = 'set' . (new UnicodeString($info['plural']))->camel()->title()->toString() . 'InDisplay';
      $this->derivatives[$plural_id] ??= $definition;
    }

    foreach (self::SINGLE_VALUE_OPTIONS as $option) {
      $this->derivatives['set' . (new UnicodeString($option))->camel()->title()->toString() . 'InDisplay'] = [
        'admin_label' => $this->t('Set @option in a Views display', ['@option' => str_replace('_', ' ', $option)]),
        'option' => $option,
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
