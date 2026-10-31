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
 * Parse the "main" key of permissions data.
 */
#[PermissionsParser(
  id: 'user_permissions_parser_main',
  label: new TranslatableMarkup('PermissionsParser: main'),
  description: new TranslatableMarkup('Parse the "main" key of permissions data.'),
  weight: 100,
  legacy: FALSE,
)]
class Main extends PermissionsParserPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a Main object.
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
    if (empty($permissions['main']['extended_help']) || !empty($permissions['main'][self::PROCESSED_KEY])) {
      return 0;
    }

    $temp = [
      'extended_help' => $this->tWrapper($permissions['main']['extended_help'], [], ['context' => 'permissions: extended help']),
    ];

    $permissions['main'][self::PROCESSED_KEY] = TRUE;

    $permissionObj->setMain($provider, $temp);

    return 1;
  }

}
