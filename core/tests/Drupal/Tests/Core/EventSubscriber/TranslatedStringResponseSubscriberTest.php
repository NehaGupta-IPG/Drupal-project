<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\EventSubscriber;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\EventSubscriber\TranslatedStringResponseSubscriber;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests Drupal\Core\EventSubscriber\TranslatedStringResponseSubscriber.
 */
#[CoversClass(TranslatedStringResponseSubscriber::class)]
#[Group('EventSubscriber')]
class TranslatedStringResponseSubscriberTest extends UnitTestCase {

  /**
   * The tested translated string response subscriber.
   */
  protected TranslatedStringResponseSubscriber $subscriber;

  /**
   * The string translation service.
   */
  protected TranslationManager $translationManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->translationManager = new TranslationManager(new LanguageDefault(['id' => 'en']));
    $this->subscriber = new TranslatedStringResponseSubscriber($this->translationManager);
  }

  /**
   * Tests that translated strings without the language context throw.
   */
  public function testThrowsWhenTranslatedStringsAndMissingCacheContext(): void {
    $response = new CacheableJsonResponse(['title' => $this->translationManager->translate('bar')]);

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('The response claims to contain translated strings, but does not provide the "languages:language_interface" cache context. Responses that output translated strings must vary by interface language.');
    $this->subscriber->onResponse(new ResponseEvent(
      $this->createStub(HttpKernelInterface::class),
      new Request(),
      HttpKernelInterface::MAIN_REQUEST,
      $response
    ));
  }

}
