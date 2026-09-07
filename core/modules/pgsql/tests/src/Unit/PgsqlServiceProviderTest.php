<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Unit;

use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Queue\QueueDatabaseFactory as CoreQueueDatabaseFactory;
use Drupal\pgsql\PgsqlServiceProvider;
use Drupal\pgsql\Queue\QueueDatabaseFactory;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests that the queue service is only overridden on PostgreSQL.
 */
#[CoversClass(PgsqlServiceProvider::class)]
#[Group('pgsql')]
class PgsqlServiceProviderTest extends UnitTestCase {

  /**
   * The connection information to restore after the test.
   */
  protected array $originalConnectionInfo;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->originalConnectionInfo = Database::getAllConnectionInfo();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach (array_keys(Database::getAllConnectionInfo()) as $key) {
      Database::removeConnection($key);
    }
    Database::setMultipleConnectionInfo($this->originalConnectionInfo);
    parent::tearDown();
  }

  /**
   * Tests that the queue service is overridden on PostgreSQL.
   */
  public function testOverridesQueueOnPostgresql(): void {
    $container = $this->alterContainerForDriver('pgsql');
    $definition = $container->getDefinition('queue.database');
    $this->assertSame(QueueDatabaseFactory::class, $definition->getClass());
    // Replacing the class must not cost the service the tag that makes the
    // queue factory able to find it, nor the connection it is given.
    $this->assertSame([[]], $definition->getTag('queue_factory'));
    $this->assertEquals([new Reference('database')], $definition->getArguments());
  }

  /**
   * Tests that installing this module on another driver leaves the queue alone.
   */
  public function testLeavesQueueAloneOnOtherDrivers(): void {
    foreach (['mysql', 'sqlite'] as $driver) {
      $container = $this->alterContainerForDriver($driver);
      $this->assertSame(CoreQueueDatabaseFactory::class, $container->getDefinition('queue.database')->getClass(), "The queue is not overridden on $driver.");
    }
  }

  /**
   * Tests that a removed queue service does not break the container build.
   */
  public function testMissingQueueService(): void {
    $container = $this->alterContainerForDriver('pgsql', FALSE);
    $this->assertFalse($container->hasDefinition('queue.database'));
  }

  /**
   * Builds a container for a driver and runs the service provider over it.
   *
   * @param string $driver
   *   The driver of the default database connection.
   * @param bool $define_queue
   *   (optional) Whether to define the queue service. Defaults to TRUE.
   *
   * @return \Drupal\Core\DependencyInjection\ContainerBuilder
   *   The altered container.
   */
  protected function alterContainerForDriver(string $driver, bool $define_queue = TRUE): ContainerBuilder {
    Database::removeConnection('default');
    Database::addConnectionInfo('default', 'default', [
      'driver' => $driver,
      'database' => 'drupal',
    ]);
    $container = new ContainerBuilder();
    if ($define_queue) {
      // The definition as core declares it, tag included.
      // @see core.services.yml
      $container->setDefinition('queue.database', (new Definition(CoreQueueDatabaseFactory::class))
        ->setArguments([new Reference('database')])
        ->addTag('queue_factory'));
    }
    (new PgsqlServiceProvider())->alter($container);
    return $container;
  }

}
