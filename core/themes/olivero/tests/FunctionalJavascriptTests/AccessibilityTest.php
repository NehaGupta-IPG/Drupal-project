<?php

namespace Drupal\Tests\olivero\FunctionalJavascriptTests;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\AxeCoreTestTrait;

/**
 * Run a basic axe-core test on some pages.
 *
 * @group olivero
 */
class AccessibilityTest extends WebDriverTestBase {

  use AxeCoreTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'olivero';

  /**
   * Test some pages.
   *
   * ::@dataProvider providerTestAnonymousPages
   */
  public function testAnonymousPages($uri) {
    $this->drupalGet($uri);
    $this->disableFailuresForImpact('moderate');
    $this->executeAxe();
  }

  /**
   * Data provider for testPages.
   *
   * @return array
   */
  public function providerTestAnonymousPages() {
    return [
      [''],
      ['user/login'],
      ['user/register'],
      ['user/password'],
    ];
  }

}
