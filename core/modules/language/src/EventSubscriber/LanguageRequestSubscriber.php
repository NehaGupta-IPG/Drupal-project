<?php

namespace Drupal\language\EventSubscriber;

use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\LanguageNegotiatorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the $request property on the language manager.
 */
class LanguageRequestSubscriber implements EventSubscriberInterface {

  /**
   * The language manager service.
   *
   * @var ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The language negotiator.
   *
   * @var LanguageNegotiatorInterface
   */
  protected $negotiator;

  /**
   * The translation service.
   *
   * @var TranslatorInterface
   */
  protected $translation;

  /**
   * The current active user.
   *
   * @var AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a LanguageRequestSubscriber object.
   *
   * @param ConfigurableLanguageManagerInterface $language_manager
   *   The language manager service.
   * @param LanguageNegotiatorInterface $negotiator
   *   The language negotiator.
   * @param TranslatorInterface $translation
   *   The translation service.
   * @param AccountInterface $current_user
   *   The current active user.
   */
  public function __construct(ConfigurableLanguageManagerInterface $language_manager, LanguageNegotiatorInterface $negotiator, TranslatorInterface $translation, AccountInterface $current_user) {
    $this->languageManager = $language_manager;
    $this->negotiator = $negotiator;
    $this->translation = $translation;
    $this->currentUser = $current_user;
  }

  /**
   * Initializes the language manager at the beginning of the request.
   *
   * @param RequestEvent $event
   *   The Event to process.
   */
  public function onKernelRequestLanguage(RequestEvent $event) {
    if ($event->isMainRequest()) {
      $this->setLanguageOverrides();
    }
  }

  /**
   * Initializes config overrides whenever the service container is rebuilt.
   */
  public function onContainerInitializeSubrequestFinished() {
    $this->setLanguageOverrides();
  }

  /**
   * Sets the language for config overrides on the language manager.
   */
  private function setLanguageOverrides() {
    $this->negotiator->setCurrentUser($this->currentUser);
    if ($this->languageManager instanceof ConfigurableLanguageManagerInterface) {
      $this->languageManager->setNegotiator($this->negotiator);
      $this->languageManager->setConfigOverrideLanguage($this->languageManager->getCurrentLanguage());
    }
    // After the language manager has initialized, set the default langcode for
    // the string translations.
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $this->translation->setDefaultLangcode($langcode);
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['onKernelRequestLanguage', 255];
    $events[DrupalKernelInterface::CONTAINER_INITIALIZE_SUBREQUEST_FINISHED][] = ['onContainerInitializeSubrequestFinished', 255];

    return $events;
  }

}
