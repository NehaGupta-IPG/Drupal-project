<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Action\Plugin\ConfigAction;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'deleteImageEffect',
  admin_label: new TranslatableMarkup('Delete Image Effect'),
  entity_types: ['image_style'],
)]
final class DeleteImageEffect implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a DeleteImageEffect object.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config action manager.
   */
  public function __construct(
    private readonly ConfigManagerInterface $configManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get(ConfigManagerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    /** @var \Drupal\image\ImageStyleInterface $imageStyle */
    $imageStyle = $this->configManager->loadConfigEntityByName($configName);
    if (!$imageStyle instanceof ImageStyleInterface) {
      throw new ConfigActionException(sprintf("The image style %s does not exist.", $configName));
    }
    try {
      // Delete the image effect.
      $imageEffect = $imageStyle->getEffect($value);
      $imageStyle->deleteImageEffect($imageEffect);
    }
    catch (PluginNotFoundException) {
      throw new ConfigActionException(sprintf("The image style %s does not have the effect %s.", $configName, $value));
    }
  }

}
