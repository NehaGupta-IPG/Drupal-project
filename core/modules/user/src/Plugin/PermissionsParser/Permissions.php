<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\PermissionsParser;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\CallableResolver;
use Drupal\user\Attribute\PermissionsParser;
use Drupal\user\Permissions\PermissionsRepositoryInterface;
use Drupal\user\PermissionsParserPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Parse remaining permissions from permissions data.
 */
#[PermissionsParser(
  id: 'user_permissions_parser_permissions',
  label: new TranslatableMarkup('PermissionsParser: permissions'),
  description: new TranslatableMarkup('Parse remaining permissions from permissions data.'),
  weight: 500,
  legacy: TRUE,
)]
class Permissions extends PermissionsParserPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a Permissions object.
   *
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TranslationInterface $stringTranslation,
    CallableResolver $callableResolver,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $stringTranslation, $callableResolver);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('callable_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function parse(array &$permissions, string $provider, PermissionsRepositoryInterface $permissionObj): int {
    if (!is_iterable($permissions)) {
      return 0;
    }

    $count = 0;

    foreach ($permissions as $key => $permission) {
      if (!empty($permission[self::PROCESSED_KEY])) {
        continue;
      }

      if (!is_array($permission)) {
        $permission = $permissions[$key] = [
          'title' => $permission,
        ];
      }

      $permissions[$key][self::PROCESSED_KEY] = TRUE;

      if (empty($permission['title'])) {
        continue;
      }

      $permission['key'] = $key;
      $permission['title'] = $this->tWrapper($permission['title'], [], ['context' => 'permissions: permission title']);
      $permission['description'] = isset($permission['description']) ? $this->tWrapper($permission['description'], [], ['context' => 'permissions: permission description']) : NULL;
      $permission['provider'] = !empty($permission['provider']) ? $permission['provider'] : $provider;
      $permission['extended_help'] = !empty($permission['extended_help']) ? $this->tWrapper($permission['extended_help'], [], ['context' => 'permissions: extended help']) : '';

      $permissionObj->addPermission($provider, $permission);
      $count++;
    }

    return $count++;
  }

}
