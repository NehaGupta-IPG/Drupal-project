<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\locale\CurrentImportStorage;
use Drupal\locale\LocaleLanguages;
use Drupal\locale\LocaleProjectNotFoundException;
use Drupal\locale\LocaleProjectRepository;
use Drupal\locale\LocaleSource;
use Drupal\locale\LocaleTranslatableProject;
use Drupal\locale\LocaleTranslationSource;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\locale\LocaleSource.
 */
#[CoversClass(LocaleSource::class)]
#[Group('locale')]
class LocaleSourceTest extends UnitTestCase {

  /**
   * Tests that missing projects throw a catchable exception, not a TypeError.
   */
  public function testLoadSourcesThrowsForMissingProjects(): void {
    $repository = $this->createStub(LocaleProjectRepository::class);
    $repository->method('getMultiple')->willReturn([]);

    $store = $this->createStub(KeyValueStoreInterface::class);
    $store->method('getMultiple')->willReturn([]);
    $keyValueFactory = $this->createStub(KeyValueFactoryInterface::class);
    $keyValueFactory->method('get')->willReturn($store);

    $source = $this->getMockBuilder(LocaleSource::class)
      ->setConstructorArgs([
        $repository,
        $this->createStub(FileSystemInterface::class),
        $this->createStub(ConfigFactoryInterface::class),
        $this->createStub(CurrentImportStorage::class),
        $keyValueFactory,
        $this->createStub(TimeInterface::class),
        $this->createStub(LocaleLanguages::class),
      ])
      ->onlyMethods(['sourceBuild'])
      ->getMock();
    $source->expects($this->never())->method('sourceBuild');

    $this->expectException(LocaleProjectNotFoundException::class);
    $source->loadSource('gone-module', 'fr');
  }

  /**
   * Tests that tracked projects still build a source object.
   */
  public function testLoadSourcesBuildsTrackedProjects(): void {
    $project = new LocaleTranslatableProject(
      'locale',
      'module',
      '11.x',
      '11.4.0',
      'translations://%project-%version.%language.po',
    );
    $built = new LocaleTranslationSource('locale', 'fr');

    $repository = $this->createStub(LocaleProjectRepository::class);
    $repository->method('getMultiple')->willReturn(['locale' => $project]);

    $store = $this->createStub(KeyValueStoreInterface::class);
    $store->method('getMultiple')->willReturn([]);
    $keyValueFactory = $this->createStub(KeyValueFactoryInterface::class);
    $keyValueFactory->method('get')->willReturn($store);

    $source = $this->getMockBuilder(LocaleSource::class)
      ->setConstructorArgs([
        $repository,
        $this->createStub(FileSystemInterface::class),
        $this->createStub(ConfigFactoryInterface::class),
        $this->createStub(CurrentImportStorage::class),
        $keyValueFactory,
        $this->createStub(TimeInterface::class),
        $this->createStub(LocaleLanguages::class),
      ])
      ->onlyMethods(['sourceBuild'])
      ->getMock();
    $source->expects($this->once())
      ->method('sourceBuild')
      ->with($project, 'fr')
      ->willReturn($built);

    $this->assertSame($built, $source->loadSource('locale', 'fr'));
  }

}
