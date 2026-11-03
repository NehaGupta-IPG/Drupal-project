<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\LanguageNegotiatorInterface;
use Drupal\Tests\language\Traits\LanguageTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\ErrorHandler\BufferingLogger;

/**
 * Tests PluginNotFoundException.
 */
#[Group('language')]
#[RunTestsInSeparateProcesses]
class LanguageNegotiatorPluginTest extends KernelTestBase {

  use LanguageTestTrait, UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language', 'user'];

  /**
   * The language manager used in this test.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The language negotiator instance being tested.
   *
   * @var \Drupal\language\LanguageNegotiatorInterface
   */
  protected LanguageNegotiatorInterface $languageNegotiator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');

    $this->languageManager = $this->container->get('language_manager');
    $this->languageNegotiator = $this->container->get('language_negotiator');
  }

  /**
   * Tests an unavailable plugin is handled correctly.
   */
  public function testLanguageNegotiatorNoPlugin(): void {
    $logger = new BufferingLogger();
    $logger_factory = $this->createMock(LoggerChannelFactory::class);
    $logger_factory->expects($this->once())
      ->method('get')
      ->with('language')
      ->willReturn($logger);
    $this->container->set('logger.factory', $logger_factory);

    $config = $this->config('language.types');
    $config->set('configurable', [LanguageInterface::TYPE_URL]);
    $config->set('negotiation.language_url.enabled', [
      self::CLASS => -3,
    ]);
    $config->save();
    try {
      $this->languageNegotiator->initializeType(LanguageInterface::TYPE_URL);
    }
    catch (PluginNotFoundException) {
      $this->fail('Plugin not found exception unhandled.');
    }
    $log_message = $logger->cleanLogs()[0];
    $this->assertEquals('error', $log_message[0]);
    $this->assertStringContainsString('The "Drupal\Tests\language\Kernel\LanguageNegotiatorPluginTest" plugin does not exist.', $log_message[1]);
  }

  /**
   * Tests the user language negotiation plugin.
   */
  public function testLanguageNegotiationUser(): void {
    $config = $this->config('language.types');
    $config->set('configurable', [LanguageInterface::TYPE_URL]);
    $config->set('negotiation.language_url.enabled', [
      LanguageNegotiationUser::METHOD_ID => -3,
    ]);
    $config->save();

    $english = $this->languageManager->getDefaultLanguage();
    $this->assertEquals(
      [LanguageNegotiatorInterface::METHOD_ID => $english],
      $this->languageNegotiator->initializeType(LanguageInterface::TYPE_URL),
    );

    $this->setCurrentUser($this->createUser());
    $this->languageNegotiator->reset();
    $this->assertEquals(
      [LanguageNegotiationUser::METHOD_ID => $english],
      $this->languageNegotiator->initializeType(LanguageInterface::TYPE_URL),
    );

    $langcode = 'af';
    $this->createLanguageFromLangcode($langcode);
    $afrikaans = $this->languageManager->getLanguage($langcode);
    $afrikaansUser = $this->createUser(values: ['preferred_langcode' => $langcode]);
    $this->setCurrentUser($afrikaansUser);
    $this->languageNegotiator->reset();
    $this->assertEquals(
      [LanguageNegotiationUser::METHOD_ID => $afrikaans],
      $this->languageNegotiator->initializeType(LanguageInterface::TYPE_URL),
    );
  }

}
