<?php

namespace Drupal\language\EventSubscriber;

use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\LanguageNegotiatorInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets the $request property on the language manager.
 */
class LanguageRequestSubscriber implements EventSubscriberInterface {

  /**
   * The language manager service.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The language negotiator.
   *
   * @var \Drupal\language\LanguageNegotiatorInterface
   */
  protected $negotiator;

  /**
   * The translation service.
   *
   * @var \Drupal\Core\StringTranslation\Translator\TranslatorInterface
   */
  protected $translation;

  /**
   * Constructs a LanguageRequestSubscriber object.
   *
   * @param \Drupal\language\ConfigurableLanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\language\LanguageNegotiatorInterface $negotiator
   *   The language negotiator.
   * @param \Drupal\Core\StringTranslation\Translator\TranslatorInterface $translation
   *   The translation service.
   * @param ?\Drupal\Core\Session\AccountInterface $current_user
   *   The current active user.
   */
  public function __construct(ConfigurableLanguageManagerInterface $language_manager, LanguageNegotiatorInterface $negotiator, TranslatorInterface $translation, ?AccountInterface $current_user = NULL) {
    $this->languageManager = $language_manager;
    $this->negotiator = $negotiator;
    $this->translation = $translation;
    if ($current_user) {
      @trigger_error('Calling ' . __CLASS__ . ' constructor with the $current_user argument is deprecated in drupal:11.5.0 and it will be removed in drupal:13.0.0. See https://www.drupal.org/node/3544400', E_USER_DEPRECATED);
    }
  }

  /**
   * Initializes the language manager at the beginning of the request.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
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
    $events[DrupalKernelInterface::CONTAINER_INITIALIZE_SUBREQUEST_FINISHED][] = [
      'onContainerInitializeSubrequestFinished',
      255,
    ];

    return $events;
  }

}
