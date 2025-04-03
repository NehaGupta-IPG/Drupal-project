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
 * Parse the "sections" key of permissions data.
 */
#[PermissionsParser(
  id: 'user_permissions_parser_sections',
  label: new TranslatableMarkup('PermissionsParser: sections'),
  description: new TranslatableMarkup('Parse the "sections" key of permissions data.'),
  weight: 300,
  legacy: FALSE,
)]
class Sections extends PermissionsParserPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a Sections object.
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
    if (empty($permissions['sections']) || !empty($permissions['sections'][self::PROCESSED_KEY]) || !is_iterable($permissions['sections'])) {
      return 0;
    }

    $permissions['sections'][self::PROCESSED_KEY] = TRUE;
    $count = 0;

    foreach ($permissions['sections'] as $section) {
      if (empty($section['title'])) {
        continue;
      }

      $section['key'] = $section['title'];
      $section['title'] = $this->tWrapper($section['title'], [], ['context' => 'permissions: section title']);
      $section['extended_help'] = !empty($section['extended_help']) ? $this->tWrapper($section['extended_help'], [], ['context' => 'permissions: extended help']) : '';

      $permissionObj->addSection($provider, $section);
      $count++;
    }

    return $count;
  }

}
