<?php

declare(strict_types=1);

namespace Drupal\navigation\Plugin\ConfigAction;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'addNavigationBlock',
  admin_label: new TranslatableMarkup('Add navigation block'),
)]
final class AddNavigationBlock implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly UuidInterface $uuidGenerator,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ConfigFactoryInterface::class),
      $container->get(UuidInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    if ($configName !== 'navigation.block_layout') {
      throw new ConfigActionException('addNavigationBlock can only be executed for the navigation.block_layout config.');
    }

    $config = $this->configFactory->getEditable('navigation.block_layout');

    $sections = $config->get('sections') ?? [
      0 => [
        'components' => [],
      ],
    ];

    // Either find the right weight/delta or adjust existing weights.
    $append = FALSE;
    $max_delta = 0;
    foreach ($sections[0]['components'] as $component) {
      if ($component['region'] === 'content') {
        if ($max_delta < $component['weight']) {
          $max_delta = $component['weight'];
        }
      }
    }

    // If the delta is either not set or is above the max delta, switch
    // into append mode and set the delta to the max + 1.
    if (!isset($value['delta']) || $value['delta'] > $max_delta) {
      $append = TRUE;
      $value['delta'] = $max_delta + 1;
    }

    $uuid = $this->uuidGenerator->generate();

    // Create the component from the recipe values.
    $component_to_add = [
      'uuid' => $uuid,
      'region' => 'content',
      'configuration' => $value['configuration'] ?? [],
      'weight' => $value['delta'],
      'additional' => $value['additional'] ?? [],
    ];

    $new_components = [];
    foreach ($sections[0]['components'] as $key => $component) {
      if ($component['region'] === 'content') {
        if (!$append && $component['weight'] === $value['delta']) {
          $new_components[$uuid] = $component_to_add;
        }
        if ($component['weight'] >= $value['delta']) {
          $component['weight']++;
        }
      }

      $new_components[$key] = $component;

      if ($append && ($component['weight'] + 1) === $value['delta']) {
        $new_components[$uuid] = $component_to_add;
      }
    }

    $config->set('sections.0.components', $new_components);
    $config->save();
  }

}
