<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\PathProcessor;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\PathProcessor\PathProcessorDecode;
use Drupal\Core\PathProcessor\PathProcessorFront;
use Drupal\Core\PathProcessor\PathProcessorManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\EventSubscriber\ConfigSubscriber;
use Drupal\language\HttpKernel\PathProcessorLanguage;
use Drupal\language\LanguageNegotiatorInterface;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\path_alias\AliasManager;
use Drupal\path_alias\PathProcessor\AliasPathProcessor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests processing of the inbound path.
 */
#[Group('PathProcessor')]
class PathProcessorTest extends UnitTestCase {

  /**
   * Configuration for the languageManager stub.
   *
   * @var \Drupal\Core\Language\LanguageInterface[]
   */
  protected $languages;

  /**
   * The language manager stub used to construct a PathProcessorLanguage object.
   */
  protected ConfigurableLanguageManagerInterface&Stub $languageManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set up some languages to be used by the language-based path processor.
    $languages = [];
    foreach (['en', 'fr'] as $langcode) {
      $language = new Language(['id' => $langcode]);
      $languages[$langcode] = $language;
    }
    $this->languages = $languages;

    // Create a language manager stub.
    $language_manager = $this->createStub(ConfigurableLanguageManagerInterface::class);
    $language_manager
      ->method('getCurrentLanguage')
      ->willReturn($languages['en']);
    $language_manager
      ->method('getLanguages')
      ->willReturn($this->languages);
    $language_manager
      ->method('getLanguageTypes')
      ->willReturn([LanguageInterface::TYPE_INTERFACE]);

    $this->languageManager = $language_manager;
  }

  /**
   * Tests resolving the inbound path to the system path.
   */
  public function testProcessInbound(): void {

    // Create an alias manager stub.
    $alias_manager = $this->createStub(AliasManager::class);

    $system_path_map = [
      // Set up one proper alias that can be resolved to a system path.
      ['/foo', NULL, '/user/1'],
      // Passing in anything else should return the same string.
      ['/fr/foo', NULL, '/fr/foo'],
      ['/fr', NULL, '/fr'],
      ['/user/login', NULL, '/user/login'],
      // Entries for when path_langcode is set to 'fr' by LanguageNegotiationUrl.
      // After the language processor strips the '/fr' prefix, the alias
      // processor will be called with 'fr' as the langcode.
      ['/foo', 'fr', '/user/1'],
      ['/user/login', 'fr', '/user/login'],
    ];

    $alias_manager
      ->method('getPathByAlias')
      ->willReturnMap($system_path_map);

    // Create a stub config factory with all config settings that will be
    // checked during this test.
    $config_factory_stub = $this->getConfigFactoryStub(
      [
        'system.site' => [
          'page.front' => '/user/login',
        ],
        'language.negotiation' => [
          'url' => [
            'prefixes' => ['fr' => 'fr'],
            'source' => LanguageNegotiationUrl::CONFIG_PATH_PREFIX,
          ],
        ],
      ]
    );

    // Create a language negotiator stub.
    $negotiator = $this->createStub(LanguageNegotiatorInterface::class);
    $negotiator
      ->method('getNegotiationMethods')
      ->willReturn([
        LanguageNegotiationUrl::METHOD_ID => [
          'class' => 'Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl',
          'weight' => 9,
        ],
      ]);
    $method = new LanguageNegotiationUrl();
    $method->setConfig($config_factory_stub);
    $method->setLanguageManager($this->languageManager);
    $negotiator
      ->method('getNegotiationMethodInstance')
      ->willReturn($method);

    // Create a user stub.
    $current_user = $this->createStub(AccountInterface::class);

    // Create a config event subscriber stub.
    $config_subscriber = $this->createStub(ConfigSubscriber::class);

    // Create the processors.
    $alias_processor = new AliasPathProcessor($alias_manager);
    $decode_processor = new PathProcessorDecode();
    $front_processor = new PathProcessorFront($config_factory_stub);
    $language_processor = new PathProcessorLanguage($config_factory_stub, $this->languageManager, $negotiator, $current_user, $config_subscriber);

    // First, test the processor manager with the processors in the incorrect
    // order. The alias processor will run before the language processor,
    // meaning aliases will not be found.
    $processor_manager = new PathProcessorManager([
      $alias_processor,
      $decode_processor,
      $front_processor,
      $language_processor,
    ]);

    // Test resolving the French homepage using the incorrect processor order.
    $test_path = '/fr';
    $request = Request::create($test_path);
    $processed = $processor_manager->processInbound($test_path, $request);
    $this->assertEquals('/', $processed, 'Processing in the incorrect order fails to resolve the system path from the empty path');

    // Test resolving an existing alias using the incorrect processor order.
    $test_path = '/fr/foo';
    $request = Request::create($test_path);
    $processed = $processor_manager->processInbound($test_path, $request);
    $this->assertEquals('/foo', $processed, 'Processing in the incorrect order fails to resolve the system path from an alias');

    // Now create a new processor manager with the processors in the correct
    // order.
    $processor_manager = new PathProcessorManager([
      $decode_processor,
      $language_processor,
      $front_processor,
      $alias_processor,
    ]);

    // Test resolving the French homepage using the correct processor order.
    $test_path = '/fr';
    $request = Request::create($test_path);
    $processed = $processor_manager->processInbound($test_path, $request);
    $this->assertEquals('/user/login', $processed, 'Processing in the correct order resolves the system path from the empty path.');

    // Test resolving an existing alias using the correct processor order.
    $test_path = '/fr/foo';
    $request = Request::create($test_path);
    $processed = $processor_manager->processInbound($test_path, $request);
    $this->assertEquals('/user/1', $processed, 'Processing in the correct order resolves the system path from an alias.');

    // Test resolving an existing alias with no language prefix. The language
    // processor will not strip any prefix or set a path_langcode, so the alias
    // processor must fall back to a NULL langcode lookup.
    $test_path = '/foo';
    $request = Request::create($test_path);
    $processed = $processor_manager->processInbound($test_path, $request);
    $this->assertEquals('/user/1', $processed, 'Processing in the correct order resolves an alias with no language prefix.');

    // Test that a direct system path with no language prefix passes through
    // unchanged, confirming the NULL-langcode alias lookup returns the path as-is.
    $test_path = '/user/login';
    $request = Request::create($test_path);
    $processed = $processor_manager->processInbound($test_path, $request);
    $this->assertEquals('/user/login', $processed, 'Processing in the correct order passes through a system path with no language prefix.');
  }

}
