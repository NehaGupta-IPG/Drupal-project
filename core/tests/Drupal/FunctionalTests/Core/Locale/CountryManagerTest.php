<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Core\Locale;

use Drupal\Tests\BrowserTestBase;

// cspell:ignore Espagne

/**
 * Tests Country Manager functionality.
 *
 * @group CountryManager
 * @covers Drupal\Core\Locale\CountryManager
 */
class CountryManagerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'locale',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'minimal';

  /**
   * Admin user account.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests the translation of country names.
   */
  public function testCountryManagerTranslate(): void {
    // Add French language.
    $edit = [
      'predefined_langcode' => 'fr',
    ];
    $this->drupalGet('/admin/config/regional/language/add');
    $this->submitForm($edit, 'Add language');

    // French country list.
    $this->drupalGet('fr/admin/config/regional/settings');
    $options = $this->assertSession()->selectExists('edit-site-default-country')->findAll('css', 'option');
    $country_ids = array_map(function ($item) {
      return $item->getValue();
    }, $options);
    $country_names = array_map(function ($item) {
      return $item->getText();
    }, $options);
    $results = array_combine($country_ids, $country_names);

    // Ensure that "Spain" is shown in French.
    $this->assertEquals('ES', array_search('Espagne', $results));
    // Zimbabwe should be sorted after Ã…land Islands.
    $this->assertLessThan(array_search('ZW', $country_ids), array_search('AX', $country_ids));

    // English country list.
    $this->drupalGet('admin/config/regional/settings');
    $options = $this->assertSession()->selectExists('edit-site-default-country')->findAll('css', 'option');
    $country_ids = array_map(function ($item) {
      return $item->getValue();
    }, $options);
    $country_names = array_map(function ($item) {
      return $item->getText();
    }, $options);
    $results = array_combine($country_ids, $country_names);

    // Ensure that "Spain" is shown in English.
    $this->assertEquals('ES', array_search('Spain', $results));
    // Zimbabwe should be sorted after Ã…land Islands.
    $this->assertLessThan(array_search('ZW', $country_ids), array_search('AX', $country_ids));

  }

}
