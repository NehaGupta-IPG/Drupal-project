<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Access;

use Drupal\Core\Access\AccessArgumentsResolverFactory;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Provides tests for the access arguments resolver factory.
 */
#[CoversClass(AccessArgumentsResolverFactory::class)]
#[Group('Access')]
#[RunTestsInSeparateProcesses]
class AccessArgumentsResolverFactoryTest extends KernelTestBase {

  /**
   * Used for checking expected languages passed to access callbacks.
   *
   * @var \Drupal\Core\Language\LanguageInterface[]
   */
  protected array $expectedLanguages = [];

  /**
   * Tests that access callback language arguments can be provided from route.
   */
  public function testAccessArgumentsResolverFactoryLanguageArguments(): void {
    foreach (['de', 'en', 'ja'] as $langcode) {
      $languages[$langcode] = $this->createStub(LanguageInterface::class);
      $languages[$langcode]->method('getId')
        ->willReturn($langcode);
    }
    $languageManager = $this->createStub(LanguageManagerInterface::class);
    $languageManager->method('getCurrentLanguage')
      ->willReturnMap([
        [LanguageInterface::TYPE_CONTENT, $languages['en']],
        [LanguageInterface::TYPE_INTERFACE, $languages['de']],
      ]);
    $languageManager->method('getDefinedLanguageTypes')
      ->willReturn([
        LanguageInterface::TYPE_CONTENT,
        LanguageInterface::TYPE_INTERFACE,
      ]);
    $languageManager->method('getLanguage')
      ->willReturnMap([
        ['de', $languages['de']],
        ['en', $languages['en']],
        ['ja', $languages['ja']],
      ]);
    $this->container->set('language_manager', $languageManager);

    // Check access on the test URLs, with no language option set. The language
    // arguments expected to be passed are the current languages.
    $url = new Url('test_language_access');
    $this->expectedLanguages = [
      LanguageInterface::TYPE_CONTENT => $languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT),
      LanguageInterface::TYPE_INTERFACE => $languageManager->getCurrentLanguage(),
    ];
    $access = $url->access(return_as_object: TRUE);
    $this->assertInstanceOf(AccessResultReasonInterface::class, $access);
    $this->assertSame('Method Drupal\KernelTests\Core\Access\AccessArgumentsResolverFactoryTest::routeAccessWithLanguageArguments has no opinion on route access with languages en (content) and de (interface).', $access->getReason());

    // Check access on the same URLs but with the language option set. The
    // expected language objects are the same as the language passed.
    $url = new Url('test_language_access', options: ['language' => $languages['ja']]);
    $this->expectedLanguages = [
      LanguageInterface::TYPE_CONTENT => $languages['ja'],
      LanguageInterface::TYPE_INTERFACE => $languages['ja'],
    ];
    $access = $url->access(return_as_object: TRUE);
    $this->assertInstanceOf(AccessResultReasonInterface::class, $access);
    $this->assertSame('Method Drupal\KernelTests\Core\Access\AccessArgumentsResolverFactoryTest::routeAccessWithLanguageArguments has no opinion on route access with languages ja (content) and ja (interface).', $access->getReason());

    // Check access on the same URLs but with the languages passed as route
    // parameters with language code values.
    $url = new Url('test_language_access', [
      'language_content_' => 'de',
      'language_interface_' => 'ja',
    ]);
    $this->expectedLanguages = [
      LanguageInterface::TYPE_CONTENT => $languages['de'],
      LanguageInterface::TYPE_INTERFACE => $languages['ja'],
    ];
    $access = $url->access(return_as_object: TRUE);
    $this->assertInstanceOf(AccessResultReasonInterface::class, $access);
    $this->assertSame('Method Drupal\KernelTests\Core\Access\AccessArgumentsResolverFactoryTest::routeAccessWithLanguageArguments has no opinion on route access with languages de (content) and ja (interface).', $access->getReason());
  }

  /**
   * Defines routes with access callbacks that have language arguments.
   *
   * @return array
   *   Render array.
   */
  #[Route(
    path: '/test-language-access',
    name: 'test_language_access',
    requirements: ['_custom_access' => self::class . ':routeAccessWithLanguageArguments'],
  )]
  public function languageAccessRoute(): array {
    return [];
  }

  /**
   * Route custom access callback with camel-cased language arguments.
   *
   * @param \Drupal\Core\Language\LanguageInterface|null $languageContent
   *   The content language with a camel-cased argument name.
   * @param \Drupal\Core\Language\LanguageInterface|null $languageInterface
   *   The interface language with a camel-cased argument name.
   * @param \Drupal\Core\Language\LanguageInterface|null $language_content
   *   The conte language with a snake-cased argument name.
   * @param \Drupal\Core\Language\LanguageInterface|null $language_interface
   *   The interface language with a snake-cased argument name.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function routeAccessWithLanguageArguments(
    ?LanguageInterface $languageContent = NULL,
    ?LanguageInterface $languageInterface = NULL,
    ?LanguageInterface $language_content = NULL,
    ?LanguageInterface $language_interface = NULL,
  ): AccessResultInterface {
    $this->assertSame($languageContent, $this->expectedLanguages[LanguageInterface::TYPE_CONTENT]);
    $this->assertSame($languageInterface, $this->expectedLanguages[LanguageInterface::TYPE_INTERFACE]);
    $this->assertSame($language_content, $this->expectedLanguages[LanguageInterface::TYPE_CONTENT]);
    $this->assertSame($language_interface, $this->expectedLanguages[LanguageInterface::TYPE_INTERFACE]);
    // Set the access object reason to the method name to use to confirm the
    // method ran.
    return new AccessResultNeutral(sprintf('Method %s has no opinion on route access with languages %s (content) and %s (interface).', __METHOD__, $languageContent?->getId() ?? 'NULL', $languageInterface?->getId() ?? 'NULL'));
  }

}
