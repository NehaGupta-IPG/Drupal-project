<?php

namespace ServicesPackage;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\help\HelpTopicPluginManagerInterface;
use Drupal\language\LanguageNegotiationMethodManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A test service.
 */
class TestService {

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory is a core service.
   * @param HelpTopicPluginManagerInterface $helpTopicManager
   *   The help topic plugin manager can be auto wired, but may not exist.
   * @param \Drupal\language\LanguageNegotiationMethodManager|null $languageNegotiationMethodManager
   *   The language module may not be installed.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected ?HelpTopicPluginManagerInterface $helpTopicManager = NULL,
    #[Autowire('@plugin.manager.language_negotiation_method')]
    protected ?LanguageNegotiationMethodManager $languageNegotiationMethodManager = NULL,
  ) {}

  /**
   * Get the configured service dependencies.
   *
   * @return array
   *   The services so that we can check if they were set up.
   */
  public function getConfiguredDependencies(): array {
    return [
      'config.factory' => $this->configFactory,
      'help' => $this->helpTopicManager,
      'language' => $this->languageNegotiationMethodManager,
    ];
  }

}
