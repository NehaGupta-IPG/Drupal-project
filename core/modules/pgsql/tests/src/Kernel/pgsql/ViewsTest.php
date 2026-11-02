<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use Drupal\pgsql\Plugin\views\query\PgsqlCastSql;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests views service.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class ViewsTest extends DriverSpecificDatabaseTestBase {

  /**
   * Tests views service.
   */
  public function testViewsService(): void {
    $this->assertFalse($this->container->has('views.cast_sql'));
    $this->enableModules(['views']);
    $this->assertInstanceOf(PgsqlCastSql::class, $this->container->get('views.cast_sql'));
    $this->assertFalse($this->container->has('pgsql.views.cast_sql'));
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $this->assertSame($container->hasDefinition('pgsql.views.cast_sql'), isset($container->getParameter('container.modules')['views']));
  }

}
