<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the navigation is shown in the preferred admin language.
 *
 * @see \Drupal\language\AdminLanguageRender
 */
#[Group('navigation')]
#[RunTestsInSeparateProcesses]
class NavigationMenuTranslationTest extends BrowserTestBase {

  /**
   * A user with permission to access the administrative navigation.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'navigation',
    'locale',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'access navigation',
      'administer languages',
      'access administration pages',
      // Makes the 'Structure' item (via Block layout) appear in the admin menu.
      'administer blocks',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that the navigation is shown in the preferred admin language.
   */
  public function testNavigationRenderedInPreferredAdminLanguage(): void {
    $langcode = 'es';
    ConfigurableLanguage::createFromLangcode($langcode)->save();

    // Enable URL negotiation (so the /es prefix selects Spanish) and the
    // 'Account administration pages' method, which takes priority when set so
    // that admin routes use the preferred admin language.
    $config = $this->config('language.types');
    $negotiation = $config->get('negotiation.language_interface.enabled');
    $negotiation['language-user-admin'] = -10;
    $negotiation['language-url'] = -8;
    $config->set('negotiation.language_interface.enabled', $negotiation)->save();

    // Configure the 'es' URL prefix.
    $this->config('language.negotiation')->set('url.prefixes.es', 'es')->save();

    // Translate two strings rendered by the navigation: 'Structure' comes from
    // the admin menu block (rendered inline in NavigationRenderer) and
    // 'View profile' from the user block (rendered in a separate lazy builder).
    // Together they exercise both places the admin-language switch is applied.
    $this->setUpTranslation($langcode, 'Structure', 'RAKENNE-TEST');
    $this->setUpTranslation($langcode, 'View profile', 'NAYTA-PROFIILI-TEST');

    // Without a preferred admin language, the navigation follows the interface
    // language of the page, so the Spanish-prefixed page shows the Spanish
    // translations.
    $this->drupalGet($langcode . '/user');
    $this->assertSession()->linkExists('RAKENNE-TEST');
    $this->assertSession()->linkExists('NAYTA-PROFIILI-TEST');

    // Configure a preferred admin language different from the page language.
    $this->adminUser->set('preferred_admin_langcode', 'en')->save();
    drupal_flush_all_caches();

    // The navigation is now rendered in the preferred admin language even on
    // the Spanish-prefixed page, while the page itself stays in Spanish.
    $this->drupalGet($langcode . '/user');
    $this->assertSession()->linkExists('Structure');
    $this->assertSession()->linkExists('View profile');
    $this->assertSession()->linkNotExists('RAKENNE-TEST');
    $this->assertSession()->linkNotExists('NAYTA-PROFIILI-TEST');
    // The page itself is still served in the negotiated interface language.
    $this->assertSession()->elementAttributeContains('css', 'html', 'lang', $langcode);
  }

  /**
   * Creates a translation for a source string.
   *
   * @param string $langcode
   *   The langcode of the translation.
   * @param string $source
   *   The untranslated source string.
   * @param string $translation
   *   The translation.
   */
  protected function setUpTranslation(string $langcode, string $source, string $translation): void {
    $storage = \Drupal::service('locale.storage');
    $string = $storage->findString(['source' => $source]) ?: $storage->createString(['source' => $source])->save();
    $storage->createTranslation([
      'lid' => $string->getId(),
      'language' => $langcode,
      'translation' => $translation,
    ])->save();
    // Make the new translation available to the string translation service.
    \Drupal::service('string_translation')->reset();
  }

}
