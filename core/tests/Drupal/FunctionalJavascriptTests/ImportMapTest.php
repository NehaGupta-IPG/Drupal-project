<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests;

use Drupal\Core\Url;

/**
 * Tests importmap and scopes.
 *
 * @group importmaps
 */
final class ImportMapTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['importmaps_test'];

  /**
   * Tests importmap functionality.
   */
  public function testImportMap(): void {
    $url = Url::fromRoute('importmaps_test.test');
    $this->drupalGet($url);
    $this->assertSession()->elementExists('css', '#importmaps-test');
    $this->assertSession()->waitForText('Root level bar');
    $this->assertSession()->pageTextNotContains('Scoped bar');

    $this->drupalGet($url->setOption('query', ['scoped' => 1]));
    $this->assertSession()->elementExists('css', '#importmaps-test');
    $this->assertSession()->waitForText('Scoped bar');
    $this->assertSession()->pageTextNotContains('Root level bar');
  }

}
