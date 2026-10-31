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
 * Parse the "permission_callbacks" key of permissions data.
 */
#[PermissionsParser(
  id: 'user_permissions_parser_permission_callbacks',
  label: new TranslatableMarkup('PermissionsParser: permission callbacks'),
  description: new TranslatableMarkup('Parse the "permission_callbacks" key of permissions data.'),
  weight: 400,
  legacy: TRUE,
)]
class PermissionCallbacks extends PermissionsParserPluginBase implements ContainerFactoryPluginInterface {

  private const CALLBACKS_KEY = 'permission_callbacks';

  /**
   * Constructs a PermissionCallbacks object.
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
    if (empty($permissions[self::CALLBACKS_KEY]) || !empty($permissions[self::CALLBACKS_KEY][self::PROCESSED_KEY]) || !is_iterable($permissions[self::CALLBACKS_KEY])) {
      return 0;
    }

    $permissions[self::CALLBACKS_KEY][self::PROCESSED_KEY] = TRUE;
    $count = 0;

    foreach ($permissions[self::CALLBACKS_KEY] as $k => $callbackDefinition) {
      if ($k == self::PROCESSED_KEY) {
        continue;
      }

      // The next will throw an exception if $callbackDefinition isn't valid.
      $callback = $this->callableResolver->getCallableFromDefinition($callbackDefinition);
      $callbackResults = call_user_func($callback);
      if (!$callbackResults || !is_iterable($callbackResults)) {
        continue;
      }

      foreach ($callbackResults as $key => $permission) {
        if (!is_array($permission)) {
          $permission = [
            'title' => $permission,
          ];
        }

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
    }

    return $count++;
  }

}
