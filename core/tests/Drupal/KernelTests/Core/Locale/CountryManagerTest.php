<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Locale;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Country Manager functionality.
 *
 * @group CountryManager
 * @covers Drupal\Core\Locale\CountryManager
 */
class CountryManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'locale',
    'locale_test',
  ];

  /**
   * Tests that hook_countries_alters() works as expected.
   */
  public function testHookCountriesAlter(): void {
    $countries = $this->container->get('country_manager')->getList();
    self::assertArrayHasKey('EB', $countries);
    self::assertSame('Elbonia', $countries['EB']);
  }

  /**
   * Tests sorting of countries.
   */
  public function testCountryManagerLocalizedSort(): void {
    $countryManager = $this->container->get('country_manager');

    $countryList = $countryManager->getLocalizedList('en');
    $countryIds = array_keys($countryList);
    // cSpell:disable-next-line
    // Fails if Zimbabwe is in front of Ã…land Islands, wrong alphabetical order.
    $this->assertLessThan(array_search('ZW', $countryIds), array_search('AX', $countryIds));
  }

}
