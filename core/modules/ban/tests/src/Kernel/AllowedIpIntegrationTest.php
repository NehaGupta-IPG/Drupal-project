<?php

declare(strict_types=1);

namespace Drupal\Tests\ban\Kernel;

use Drupal\ban\BanIpManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Verifies integration between Allowed IPs and BanIpManager.
 *
 * @group ban
 */
final class AllowedIpIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ban',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('ban', ['ban_ip']);
    $this->installConfig(['ban']);
  }

  /**
   * Counts DB ban rows for a given IP.
   */
  protected function countBanRows(string $ip): int {
    /** @var \Drupal\Core\Database\Connection $connection */
    $connection = $this->container->get('database');
    return (int) $connection->query(
      'SELECT COUNT(*) FROM {ban_ip} WHERE [ip] = :ip',
      [':ip' => $ip]
    )->fetchField();
  }

  public function testBanNormalIpCreatesRow(): void {
    /** @var \Drupal\ban\BanIpManagerInterface $manager */
    $manager = $this->container->get(BanIpManagerInterface::class);
    $ip = '198.51.100.10';

    $this->assertSame(0, $this->countBanRows($ip));
    $this->assertFalse($manager->isBanned($ip));

    $manager->banIp($ip);

    $this->assertSame(1, $this->countBanRows($ip));
    $this->assertTrue($manager->isBanned($ip));
  }

  public function testBanAllowedIpNoEffect(): void {
    /** @var \Drupal\ban\BanIpManagerInterface $manager */
    $manager = $this->container->get(BanIpManagerInterface::class);
    $ip = '203.0.113.77';

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('ban_allowed_ip');
    $this->assertInstanceOf(EntityStorageInterface::class, $storage);

    // Use a safe ID (no regex; avoid nullable return).
    $id = strtr($ip, ['.' => '_', ':' => '_']);

    $storage->create([
      'id' => $id,
      'allowedIp' => $ip,
    ])->save();

    $this->assertFalse($manager->isBanned($ip));
    $this->assertSame(0, $this->countBanRows($ip));

    // Event subscriber should veto adding to the ban table.
    $manager->banIp($ip);

    $this->assertSame(0, $this->countBanRows($ip));
    $this->assertFalse($manager->isBanned($ip));
  }

}
