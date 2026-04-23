<?php

declare(strict_types=1);

namespace Drupal\Tests\toolbar\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the toolbar icon class remains for translated menu items.
 */
#[Group('toolbar')]
#[RunTestsInSeparateProcesses]
class ToolbarMenuTranslationTest extends BrowserTestBase {

  /**
   * A user with permission to access the administrative toolbar.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'toolbar',
    'toolbar_test',
    'locale',
    'locale_test',
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

    // Create an administrative user and log it in.
    $this->adminUser = $this->drupalCreateUser([
      'access toolbar',
      'translate interface',
      'administer languages',
      'access administration pages',
      'administer blocks',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that toolbar classes don't change when adding a translation.
   */
  public function testToolbarClasses(): void {
    $langcode = 'es';

    // Add Spanish.
    $edit['predefined_langcode'] = $langcode;
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm($edit, 'Add language');

    // The menu item 'Structure' in the toolbar will be translated.
    $menu_item = 'Structure';

    // Visit a page that has the string on it so it can be translated.
    $this->drupalGet($langcode . '/admin/structure');

    // Check that the class is on the item before we translate it.
    $this->assertSession()->elementsCount('xpath', '//a[contains(@class, "icon-system-admin-structure")]', 1);

    // Translate the menu item.
    $menu_item_translated = $this->randomMachineName();
    $this->addLocalizedString($langcode, $menu_item, $menu_item_translated);

    // Go to another page in the custom language and make sure the menu item
    // was translated.
    $this->drupalGet($langcode . '/admin/structure');
    $this->assertSession()->pageTextContains($menu_item_translated);

    // Toolbar icons are included based on the presence of a specific class on
    // the menu item. Ensure that class also exists for a translated menu item.
    $xpath = $this->xpath('//a[contains(@class, "icon-system-admin-structure")]');
    $this->assertCount(1, $xpath, 'The menu item class is the same.');
  }

  /**
   * Tests that the toolbar is shown in the preferred admin language.
   */
  public function testToolbarRenderedInPreferredAdminLanguage(): void {
    // Enable the 'Account administration pages' language detection.
    $this->drupalGet('admin/config/regional/language/detection');
    $this->submitForm(['language_interface[enabled][language-user-admin]' => TRUE], 'Save settings');

    $langcode = 'es';

    // Add Spanish.
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm(['predefined_langcode' => $langcode], 'Add language');

    // The menu item 'Structure' and 'View profile' in the toolbar will be
    // translated.
    $menu_item_structure = 'Structure';
    $menu_item_view_profile = 'View profile';

    // Visit a page that has the string on it so it can be translated.
    $this->drupalGet($langcode . '/admin/structure');
    $menu_item_structure_translated = $this->randomMachineName();
    $this->addLocalizedString($langcode, $menu_item_structure, $menu_item_structure_translated);

    // Add a translation for a menu item added using user_toolbar().
    $menu_item_view_profile_translated = $this->randomMachineName();
    $this->addLocalizedString($langcode, $menu_item_view_profile, $menu_item_view_profile_translated);

    // Go to another page in the custom language and make sure the menu item
    // was translated.
    $this->drupalGet($langcode . '/user');
    $this->assertSession()->elementContains('css', '#toolbar-link-system-admin_structure', $menu_item_structure_translated);
    $this->assertSession()->elementContains('css', '#toolbar-item-user-tray a[title="User account"]', $menu_item_view_profile_translated);

    // Configure a preferred admin language.
    $this->adminUser->set('preferred_admin_langcode', 'en');
    $this->adminUser->save();

    drupal_flush_all_caches();

    // Go to another page in the custom language and make sure the menu item
    // is shown in the preferred admin language.
    $this->drupalGet($langcode . '/user');
    $this->assertSession()->elementContains('css', '#toolbar-link-system-admin_structure', $menu_item_structure);
    $this->assertSession()->elementContains('css', '#toolbar-item-user-tray a[title="User account"]', $menu_item_view_profile);
  }

  /**
   * Add a localized string.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $string
   *   The string to translate.
   * @param string $translation
   *   The string translation.
   */
  protected function addLocalizedString(string $langcode, string $string, string $translation): void {
    // Search for the label.
    $search = [
      'string' => $string,
      'langcode' => $langcode,
      'translation' => 'untranslated',
    ];
    $this->drupalGet('admin/config/regional/translate');
    $this->submitForm($search, 'Filter');
    // Make sure will be able to translate the label.
    $this->assertSession()->pageTextNotContains('No strings available.');

    $textarea = current($this->xpath('//textarea'));

    $lid = (string) $textarea->getAttribute('name');
    $this->submitForm([$lid => $translation], 'Save translations');

    // Search for the translated menu item.
    $search = [
      'string' => $string,
      'langcode' => $langcode,
      'translation' => 'translated',
    ];
    $this->drupalGet('admin/config/regional/translate');
    $this->submitForm($search, 'Filter');
    // Make sure the menu item string was translated.
    $this->assertSession()->pageTextContains($translation);
  }

}
