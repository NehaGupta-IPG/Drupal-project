<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Action;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests language-aware caching in the action plugin manager.
 */
#[CoversClass(ActionManager::class)]
#[Group('Action')]
class ActionManagerTest extends UnitTestCase {

  /**
   * The module handler used by the action manager under test.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Namespaces passed to the plugin manager.
   *
   * The unit tests in this class never reach plugin discovery, so an empty
   * traversable is sufficient.
   */
  protected \ArrayObject $namespaces;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->moduleHandler = $this->createStub(ModuleHandlerInterface::class);
    $this->namespaces = new \ArrayObject();
  }

  /**
   * Tests the cache key is suffixed with the current interface langcode.
   */
  public function testConstructWithLanguageManagerKeysCacheByLanguage(): void {
    $language_manager = $this->createStub(LanguageManagerInterface::class);
    $language_manager->method('getCurrentLanguage')->willReturn(new Language(['id' => 'de']));

    $manager = new ActionManager($this->namespaces, $this->createStub(CacheBackendInterface::class), $this->moduleHandler, $language_manager);

    $cache_key = (new \ReflectionProperty($manager, 'cacheKey'))->getValue($manager);
    $this->assertSame('action_info:de', $cache_key);
  }

  /**
   * Tests clearing cached definitions removes the bin for every language.
   */
  public function testClearCachedDefinitionsDeletesPerLanguageCids(): void {
    $language_manager = $this->createStub(LanguageManagerInterface::class);
    $language_manager->method('getCurrentLanguage')->willReturn(new Language(['id' => 'en']));
    $language_manager->method('getLanguages')->willReturn([
      'en' => new Language(['id' => 'en']),
      'de' => new Language(['id' => 'de']),
      'fr' => new Language(['id' => 'fr']),
    ]);

    $cache_backend = $this->createMock(CacheBackendInterface::class);
    $cache_backend->expects($this->once())
      ->method('deleteMultiple')
      ->with(['action_info:en', 'action_info:de', 'action_info:fr']);

    $manager = new ActionManager($this->namespaces, $cache_backend, $this->moduleHandler, $language_manager);
    $manager->clearCachedDefinitions();
  }

  /**
   * Tests the deprecation when constructed without a language manager.
   */
  #[IgnoreDeprecations]
  public function testConstructWithoutLanguageManagerIsDeprecated(): void {
    $this->expectUserDeprecationMessage('Calling Drupal\Core\Action\ActionManager::__construct() without the $languageManager argument is deprecated in drupal:11.4.0 and will be required in drupal:12.0.0. See https://www.drupal.org/node/3589007');
    new ActionManager($this->namespaces, $this->createStub(CacheBackendInterface::class), $this->moduleHandler);
  }

}
