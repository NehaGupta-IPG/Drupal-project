<?php

namespace Drupal\block_content\Plugin\Derivative;

use Drupal\block_content\MissingBlockContentEntitySubscriber;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieves block plugin definitions for all content blocks.
 */
class BlockContent extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Constructs a BlockContent object.
   */
  public function __construct(
    protected readonly EntityStorageInterface $blockContentStorage,
    protected readonly MissingBlockContentEntitySubscriber $missingBlockContentEntitySubscriber,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')->getStorage('block_content'),
      $container->get(MissingBlockContentEntitySubscriber::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $block_contents = $this->blockContentStorage->loadByProperties(['reusable' => TRUE]);
    // Reset the discovered definitions.
    $this->derivatives = [];
    /** @var \Drupal\block_content\Entity\BlockContent $block_content */
    foreach ($block_contents as $block_content) {
      $this->derivatives[$block_content->uuid()] = $base_plugin_definition;
      $this->derivatives[$block_content->uuid()]['admin_label'] = $block_content->label() ?? ($block_content->type->entity->label() . ': ' . $block_content->id());
      $this->derivatives[$block_content->uuid()]['config_dependencies']['content'] = [
        $block_content->getConfigDependencyName(),
      ];
    }
    // Missing items that were found during config import.
    // @see \Drupal\block_content\EventSubscriber\MissingBlockContentEntitySubscriber::onMissingContent
    // @see \Drupal\block_content\Plugin\Block\BlockContentBlock::build
    foreach ($this->missingBlockContentEntitySubscriber->getMissing() as $uuid => $details) {
      if (!isset($this->derivatives[$uuid])) {
        $this->derivatives[$uuid] = $base_plugin_definition;
        $this->derivatives[$uuid]['admin_label'] = new TranslatableMarkup('Missing block content');
        $this->derivatives[$uuid]['config_dependencies']['content'] = [
          sprintf('block_content:%s:%s', $details['bundle'], $uuid),
        ];
      }
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
