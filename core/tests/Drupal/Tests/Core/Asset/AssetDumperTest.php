<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Asset;

use Drupal\Core\Asset\AssetDumper;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\Exception\FileWriteException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the asset dumper.
 */
#[CoversClass(AssetDumper::class)]
#[Group('Asset')]
class AssetDumperTest extends UnitTestCase {

  /**
   * The URI to dump to, which does not exist yet.
   */
  protected string $uri;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->uri = $this->root . '/sites/simpletest/does-not-exist/css_test.css';

    $container = new ContainerBuilder();
    $container->set('config.factory', $this->getConfigFactoryStub([
      'system.performance' => ['css' => ['compress' => FALSE]],
    ]));
    \Drupal::setContainer($container);
  }

  /**
   * Tests that the URI is returned when the asset is saved.
   */
  public function testDumpToUri(): void {
    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->expects($this->once())
      ->method('saveData')
      ->with('body {}', $this->uri)
      ->willReturn($this->uri);

    $dumper = new AssetDumper($file_system);
    $this->assertSame($this->uri, $dumper->dumpToUri('body {}', 'css', $this->uri));
  }

  /**
   * Tests that an empty string is returned when the asset cannot be saved.
   */
  public function testDumpToUriFailure(): void {
    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->expects($this->once())
      ->method('saveData')
      ->willThrowException(new FileWriteException('Failed to write.'));

    $dumper = new AssetDumper($file_system);
    $this->assertSame('', $dumper->dumpToUri('body {}', 'css', $this->uri));
  }

}
