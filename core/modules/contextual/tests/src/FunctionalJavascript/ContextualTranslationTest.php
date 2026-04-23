<?php

declare(strict_types=1);

namespace Drupal\Tests\contextual\FunctionalJavascript;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\locale\StringStorageInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests contextual link translation.
 */
#[Group('contextual')]
#[RunTestsInSeparateProcesses]
class ContextualTranslationTest extends WebDriverTestBase {

  use ContextualLinkClickTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'contextual',
    'language',
    'locale',
    'node',
    'system',
  ];

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The locale storage.
   *
   * @var \Drupal\locale\StringStorageInterface
   */
  protected StringStorageInterface $localeStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->languageManager = $this->container->get('language_manager');
    $this->localeStorage = $this->container->get('locale.storage');

    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('local_tasks_block');

    $this->adminUser = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($this->adminUser);

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    ConfigurableLanguage::createFromLangcode('nl')->save();
    $this->rebuildContainer();

    // Enable the 'Account administration pages' language detection.
    $this->drupalGet('admin/config/regional/language/detection');
    $this->submitForm(['language_interface[enabled][language-user-admin]' => TRUE], 'Save settings');
  }

  /**
   * Tests that contextual links are shown in the preferred admin language.
   */
  public function testContextualLinksPreferredAdminLanguage(): void {
    // Create a node and visit the translated page so new translation labels
    // are added.
    $nl_language = $this->languageManager->getLanguage('nl');
    $node1 = $this->drupalCreateNode(['type' => 'page']);
    $this->drupalGet($node1->toUrl('canonical', ['language' => $nl_language]));

    // Add a translation for the 'Edit' string.
    $edit_translation = $this->randomMachineName();
    $this->drupalGet('admin/config/regional/translate');
    $this->submitForm(['string' => 'Edit', 'langcode' => 'nl'], 'Filter');
    $textarea = current($this->xpath('//textarea'));
    $lid = (string) $textarea->getAttribute('name');
    $this->submitForm([$lid => $edit_translation], 'Save translations');

    // Configure a preferred admin language.
    $this->adminUser->set('preferred_admin_langcode', 'nl');
    $this->adminUser->save();

    // The edit link text should be using the translated string.
    $this->drupalGet($node1->toUrl('canonical'));
    $this->clickContextualLink('article', $edit_translation);
    $this->assertSession()->addressEquals($node1->toUrl('edit-form'));

    // Change the preferred admin language.
    $this->adminUser->set('preferred_admin_langcode', 'en');
    $this->adminUser->save();

    // The edit link text should be using the english string.
    $this->drupalGet($node1->toUrl('canonical'));
    $this->clickContextualLink('article', 'Edit');
    $this->assertSession()->addressEquals($node1->toUrl('edit-form'));
  }

}
