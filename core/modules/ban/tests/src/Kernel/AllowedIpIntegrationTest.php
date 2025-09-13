<?php

declare(strict_types=1);

namespace Drupal\Tests\ban\Kernel;

use Drupal\ban\BanIpManagerInterface;
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
    // Ban module uses a simple table for bans.
    $this->installSchema('ban', ['ban_ip']);
    // Ensure config system is ready and ban's default config is available.
    $this->installConfig(['ban']);
  }

  /**
   * Helper to count DB ban rows for a given IP.
   */
  protected function countBanRows(string $ip): int {
    $connection = $this->container->get('database');
    return (int) $connection->query(
      "SELECT COUNT(*) FROM {ban_ip} WHERE [ip] = :ip",
      [':ip' => $ip]
    )->fetchField();
  }

  /**
   * Tests that banning a normal IP creates a ban row.
   */
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

  /**
   * Tests that attempting to ban an IP on the allowed list has no effect.
   */
  public function testBanAllowedIpNoEffect(): void {
    /** @var \Drupal\ban\BanIpManagerInterface $manager */
    $manager = $this->container->get(BanIpManagerInterface::class);
    $ip = '203.0.113.77';

    // Create Allowed IP config entity.
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('ban_allowed_ip');
    $this->assertInstanceOf(EntityStorageInterface::class, $storage);

    // Use the IP value as the entity ID (simple, unique).
    $storage->create([
      'id' => preg_replace('/[^A-Za-z0-9]+/', '_', $ip),
      'allowedIp' => $ip,
    ])->save();

    // Ensure the IP is not considered banned.
    $this->assertFalse($manager->isBanned($ip));
    $this->assertSame(0, $this->countBanRows($ip));

    // Try to ban an allowed IP: should be vetoed by the event subscriber.
    $manager->banIp($ip);

    // No DB row created; still not banned.
    $this->assertSame(0, $this->countBanRows($ip));
    $this->assertFalse($manager->isBanned($ip));
  }

}
