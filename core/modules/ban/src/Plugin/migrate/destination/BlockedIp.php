<?php

namespace Drupal\ban\Plugin\migrate\destination;

use Drupal\ban\BanIpManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateDestination;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Destination for blocked IP addresses.
 */
#[MigrateDestination('blocked_ip')]
class BlockedIp extends DestinationBase implements ContainerFactoryPluginInterface {

  /**
   * The IP ban manager.
   *
   * @var \Drupal\ban\BanIpManagerInterface
   */
  protected $banManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, BanIpManagerInterface $ban_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->banManager = $ban_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('ban.ip_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return ['ip' => ['type' => 'string']];
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'ip' => $this->t('The blocked IP address.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    $this->banManager->banIp($row->getDestinationProperty('ip'));

    return ['ip' => $row->getDestinationProperty('ip')];
  }

}
