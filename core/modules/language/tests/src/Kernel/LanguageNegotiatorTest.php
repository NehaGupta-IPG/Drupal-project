<?php

namespace Drupal\Tests\language\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUI;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrlFallback;

/**
 * Tests the language negotiator methods.
 *
 * @group language
 */
class LanguageNegotiatorTest extends LanguageTestBase {

  /**
   * The language negotiator.
   *
   * @var \Drupal\language\LanguageNegotiatorInterface
   */
  protected $languageNegotiator;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['language'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config = $this->config('language.types');
    $config->set('configurable', [LanguageInterface::TYPE_URL]);
    $config->set('negotiation.language_url.enabled', [
      LanguageNegotiationUrl::METHOD_ID => 2,
      LanguageNegotiationUrlFallback::METHOD_ID => 1,
      LanguageNegotiationUI::METHOD_ID => -1,
    ]);
    $config->save();

    $this->languageNegotiator = $this->container->get('language_negotiator');
  }

  /**
   * Validate that negotiation methods are returning sorted values.
   */
  public function testNegotiatorPrioritiesOrder() {
    $expected_array = [
      [
        'id' => 'language-interface',
      ],
      [
        'id' => 'language-url-fallback',
      ],
      [
        'id' => 'language-url',
      ],
    ];
    $negotiated_languages = [];
    foreach ($this->languageNegotiator->getNegotiationMethods('language_url') as $method_id => $method) {
      $negotiated_languages[] = [
        'id' => $method['id'],
      ];
    }
    $this->assertEquals($expected_array, $negotiated_languages);
  }

  /**
   * Validate that setting.php overrides negotiation methods are working.
   */
  public function testLanguageNegotiatorWithOverrides() {
    // Set up an override.
    $GLOBALS['config']['language.types']['negotiation']['language_interface']['enabled'] = ['language-user-admin' => -21];

    $languageNegotiator = $this->container->get('language_negotiator');
    $method_keys = array_keys($languageNegotiator->getNegotiationMethods('language_interface'));
    $this->assertEquals('language-user-admin', $method_keys[0]);
  }

}
