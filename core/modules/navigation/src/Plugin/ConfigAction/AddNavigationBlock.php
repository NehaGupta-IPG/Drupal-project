<?php

declare(strict_types=1);

namespace Drupal\navigation\Plugin\ConfigAction;

use Drupal\Component\Utility\SortArray;
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
    $components = $config->get('sections.0.components');
    if (!is_array($components)) {
      throw new ConfigActionException('Unable to load the Navigation layout.');
    }

    // The delta is the position of the new block among the blocks of the
    // content region, ordered by weight, and defaults to the first position. A
    // delta beyond the last position appends the block.
    $content = array_filter($components, fn (array $component): bool => $component['region'] === 'content');
    uasort($content, [SortArray::class, 'sortByWeightElement']);
    $uuids = array_keys($content);
    $delta = max(0, min((int) ($value['delta'] ?? 0), count($uuids)));

    $uuid = $this->uuidGenerator->generate();
    $components[$uuid] = [
      'uuid' => $uuid,
      'region' => 'content',
      'weight' => 0,
      'configuration' => $value['configuration'] ?? [],
      'additional' => $value['additional'] ?? [],
    ];

    if ($delta === count($uuids)) {
      // Append the block after the last one.
      $weights = array_column($content, 'weight');
      $components[$uuid]['weight'] = $weights ? max($weights) + 1 : 0;
    }
    else {
      // Take over the weight of the block currently at the requested position
      // and move that block and all the following ones down by one.
      $weight = $content[$uuids[$delta]]['weight'];
      $components[$uuid]['weight'] = $weight++;
      foreach (array_slice($uuids, $delta) as $existing_uuid) {
        $components[$existing_uuid]['weight'] = $weight++;
      }
    }

    $config->set('sections.0.components', $components)->save();
  }

}
