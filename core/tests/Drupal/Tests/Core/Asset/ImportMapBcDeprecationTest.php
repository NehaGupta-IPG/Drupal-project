<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Asset;

use Drupal\Core\Ajax\AjaxResponseAttachmentsProcessor;
use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Asset\AssetResolver;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\ImportMapBuilderInterface;
use Drupal\Core\Asset\LibrariesDirectoryFileFinder;
use Drupal\Core\Asset\LibraryDependencyResolverInterface;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Asset\LibraryDiscoveryParser;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Render\HtmlResponseAttachmentsProcessor;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Utility\YamlCacheCollector;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the deprecated constructor signatures of the import map services.
 *
 * Every service whose constructor gained an argument for the import map keeps a
 * backwards compatible fallback until Drupal 13. These tests pin down the
 * deprecation messages, so that removing a fallback is a deliberate act.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(AssetResolver::class)]
#[CoversClass(LibraryDiscoveryParser::class)]
#[CoversClass(HtmlResponseAttachmentsProcessor::class)]
#[CoversClass(AjaxResponseAttachmentsProcessor::class)]
#[Group('Asset')]
class ImportMapBcDeprecationTest extends UnitTestCase {

  /**
   * The deprecation link shared by the import map deprecations.
   */
  protected const LINK = 'https://www.drupal.org/project/drupal/issues/3398525';

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::setContainer($this->createMock(ContainerInterface::class));
    parent::tearDown();
  }

  /**
   * Returns a logger factory producing a no-op logger.
   *
   * @return \Drupal\Core\Logger\LoggerChannelFactoryInterface
   *   The logger factory.
   */
  protected function getLoggerFactory(): LoggerChannelFactoryInterface {
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    return $factory;
  }

  /**
   * Calls a callable and returns the triggered user deprecation messages.
   *
   * @param callable $callable
   *   The callable to invoke.
   *
   * @return string[]
   *   The triggered messages.
   */
  protected function collectDeprecations(callable $callable): array {
    $messages = [];
    set_error_handler(static function (int $severity, string $message) use (&$messages): bool {
      $messages[] = $message;
      return TRUE;
    }, E_USER_DEPRECATED);
    try {
      $callable();
    }
    finally {
      restore_error_handler();
    }
    return $messages;
  }

  /**
   * Returns a container that provides the services the fallbacks look up.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   A container with stub definitions.
   */
  protected function getContainer(): ContainerInterface {
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
      ->willReturnCallback(fn(string $id) => match ($id) {
        'asset.import_map_builder' => $this->createMock(ImportMapBuilderInterface::class),
        'asset.importmap.renderer', 'asset.modulepreload.renderer' => $this->createMock(AssetCollectionRendererInterface::class),
        'logger.factory' => $this->getLoggerFactory(),
        'file_url_generator', 'asset.query_string' => $this->createMock(FileUrlGeneratorInterface::class),
        default => NULL,
      });
    return $container;
  }

  /**
   * Tests the deprecated AssetResolver constructor.
   */
  public function testAssetResolverDeprecations(): void {
    \Drupal::setContainer($this->getContainer());
    $messages = $this->collectDeprecations(fn() => new AssetResolver(
      $this->createMock(LibraryDiscoveryInterface::class),
      $this->createMock(LibraryDependencyResolverInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
      $this->createMock(ThemeManagerInterface::class),
      $this->createMock(LanguageManagerInterface::class),
      $this->createMock(CacheBackendInterface::class),
      $this->createMock(ThemeHandlerInterface::class),
    ));
    $this->assertContains('Constructing AssetResolver without a logger is deprecated in drupal:12.0.0 and the argument will be required in drupal:13.0.0. See ' . static::LINK, $messages);
    $this->assertContains('Constructing AssetResolver without an import map builder is deprecated in drupal:12.0.0 and the argument will be required in drupal:13.0.0. See ' . static::LINK, $messages);
  }

  /**
   * Tests the deprecated LibraryDiscoveryParser constructor.
   *
   * The fallback builds the processor from the dependencies the parser already
   * has, so no container is needed.
   */
  public function testLibraryDiscoveryParserDeprecation(): void {
    $messages = $this->collectDeprecations(fn() => new LibraryDiscoveryParser(
      '/app/root',
      $this->createMock(ModuleHandlerInterface::class),
      $this->createMock(ThemeManagerInterface::class),
      $this->createMock(StreamWrapperManagerInterface::class),
      $this->createMock(LibrariesDirectoryFileFinder::class),
      $this->createMock(ExtensionPathResolver::class),
      $this->createMock(ComponentPluginManager::class),
      $this->createMock(YamlCacheCollector::class),
    ));
    $this->assertContains('Constructing LibraryDiscoveryParser without an import map processor is deprecated in drupal:12.0.0 and the argument will be required in drupal:13.0.0. See ' . static::LINK, $messages);
  }

  /**
   * Tests the deprecated HtmlResponseAttachmentsProcessor constructor.
   */
  public function testHtmlResponseAttachmentsProcessorDeprecations(): void {
    \Drupal::setContainer($this->getContainer());
    $messages = $this->collectDeprecations(fn() => new HtmlResponseAttachmentsProcessor(
      $this->createMock(AssetResolverInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(AssetCollectionRendererInterface::class),
      $this->createMock(AssetCollectionRendererInterface::class),
      new RequestStack(),
      $this->createMock(RendererInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
      $this->createMock(LanguageManagerInterface::class),
      $this->createMock(FileUrlGeneratorInterface::class),
    ));
    $this->assertContains('Constructing HtmlResponseAttachmentsProcessor without an import map renderer is deprecated in drupal:12.0.0 and the argument will be required in drupal:13.0.0. See ' . static::LINK, $messages);
    $this->assertContains('Constructing HtmlResponseAttachmentsProcessor without a module preload renderer is deprecated in drupal:12.0.0 and the argument will be required in drupal:13.0.0. See ' . static::LINK, $messages);
  }

  /**
   * Tests the deprecated AjaxResponseAttachmentsProcessor constructor.
   */
  public function testAjaxResponseAttachmentsProcessorDeprecation(): void {
    \Drupal::setContainer($this->getContainer());
    $messages = $this->collectDeprecations(fn() => new AjaxResponseAttachmentsProcessor(
      $this->createMock(AssetResolverInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(AssetCollectionRendererInterface::class),
      $this->createMock(AssetCollectionRendererInterface::class),
      new RequestStack(),
      $this->createMock(RendererInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
      $this->createMock(LanguageManagerInterface::class),
    ));
    $this->assertContains('Constructing AjaxResponseAttachmentsProcessor without a module preload renderer is deprecated in drupal:12.0.0 and the argument will be required in drupal:13.0.0. See ' . static::LINK, $messages);
  }

}
