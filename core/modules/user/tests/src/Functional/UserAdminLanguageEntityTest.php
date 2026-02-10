<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional;

use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\user\UserInterface;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that admin language negotiation does not override entity_test translations.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserAdminLanguageEntityTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'content_translation',
    'content_translation_test',
    'user',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The admin user.
   */
  protected UserInterface $adminUser;

  /**
   * Languages added for the test.
   *
   * @var string[]
   */
  protected array $langcodes;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an admin user with entity permissions.
    $this->adminUser = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer entity_test content',
      'view test entity',
      'view test entity translations',
      'translate any entity',
    ]);
    $this->drupalLogin($this->adminUser);

    // Add additional languages.
    $this->langcodes = ['fr', 'it'];
    foreach ($this->langcodes as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }

    // Rebuild container to pick up new languages.
    $this->rebuildContainer();

    // Enable admin language negotiation.
    $this->drupalGet('admin/config/regional/language/detection');
    $this->submitForm([
      'language_interface[enabled][language-user-admin]' => TRUE,
      'language_interface[enabled][language-url]' => TRUE,
      'language_interface[weight][language-user-admin]' => -10,
      'language_interface[weight][language-url]' => -8,
    ], 'Save settings');
  }

  /**
   * Tests that admin language does not override entity_test translations.
   */
  public function testAdminLanguageDoesNotOverrideEntityTranslations(): void {
    // Create entity with translations.
    $entity = EntityTestMul::create(['name' => 'EN ' . $this->randomMachineName()]);
    foreach ($this->langcodes as $langcode) {
      $entity->addTranslation($langcode, [
        'name' => strtoupper($langcode) . ' ' . $this->randomMachineName(),
      ]);
    }
    $entity->save();

    // Set the admin user's preferred admin language to EN.
    $this->drupalGet('user/' . $this->adminUser->id() . '/edit');
    $this->submitForm([
      'preferred_admin_langcode' => 'en',
    ], 'Save');

    // Check each translation URL.
    foreach ($this->langcodes as $langcode) {
      $language = \Drupal::languageManager()->getLanguage($langcode);
      $url = $entity->toUrl('canonical', ['language' => $language]);
      $this->drupalGet($url);

      // Assert the translated label is shown.
      $translated_label = $entity->getTranslation($langcode)->label();
      $this->assertSession()->pageTextContains($translated_label);

      // Assert the English label is NOT shown.
      $english_label = $entity->getTranslation('en')->label();
      $this->assertSession()->pageTextNotContains($english_label);
    }
  }

}
