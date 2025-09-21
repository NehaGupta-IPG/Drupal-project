<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the ConfigurableLanguage entity.
 *
 * @group language
 * @coversDefaultClass \Drupal\language\ConfigurableLanguageManager
 */
class ConfigurableLanguageManagerTest extends LanguageTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user'];

  /**
   * The language negotiator.
   *
   * @var \Drupal\language\LanguageNegotiatorInterface
   */
  protected $languageNegotiator;

  /**
   * The language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');

    $this->languageNegotiator = $this->container->get('language_negotiator');
    $this->languageManager = $this->container->get('language_manager');
  }

  /**
   * @covers ::getLanguageSwitchLinks
   */
  public function testLanguageSwitchLinks(): void {
    $this->languageNegotiator->setCurrentUser($this->prophesize('Drupal\Core\Session\AccountInterface')->reveal());
    $this->languageManager->getLanguageSwitchLinks(LanguageInterface::TYPE_INTERFACE, new Url('<current>'));
  }

  /**
   * @covers ::setCurrentLanguage
   */
  public function testSetCurrentLanguage() {
    $this->assertEquals('en', \Drupal::languageManager()
      ->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)->getId());

    $language_code = 'es';
    $current_language = \Drupal::languageManager()
      ->setCurrentLanguage(ConfigurableLanguage::createFromLangcode($language_code), LanguageInterface::TYPE_INTERFACE);
    $this->assertEquals('en', $current_language->getId());

    $this->assertEquals($language_code, \Drupal::languageManager()
      ->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)->getId());
  }

}
