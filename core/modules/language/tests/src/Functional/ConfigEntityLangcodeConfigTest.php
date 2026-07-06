<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests config entity language selector visibility.
 */
#[Group('language')]
#[RunTestsInSeparateProcesses]
class ConfigEntityLangcodeConfigTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'menu_ui',
    'taxonomy',
    'views',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An administrative user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer languages',
      'administer menu',
      'administer site configuration',
      'administer taxonomy',
      'administer views',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests langcode selector visibility on configurable config entity forms.
   */
  public function testLangcodeVisibility(): void {
    $this->drupalGet('admin/config/regional/config-language');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertLangcodeFieldVisible(FALSE);

    $this->drupalGet('admin/config/regional/config-language');
    $this->submitForm([
      'show_langcode[menu]' => TRUE,
      'show_langcode[date_format]' => TRUE,
      'show_langcode[taxonomy_vocabulary]' => TRUE,
      'show_langcode[view]' => TRUE,
    ], 'Save configuration');

    $this->assertTrue((bool) $this->config('language.config_type_settings')->get('show_langcode.view'));

    $this->assertLangcodeFieldVisible(TRUE);

    $this->drupalGet('admin/config/regional/config-language');
    $this->submitForm([
      'show_langcode[menu]' => FALSE,
      'show_langcode[date_format]' => FALSE,
      'show_langcode[taxonomy_vocabulary]' => FALSE,
      'show_langcode[view]' => FALSE,
    ], 'Save configuration');

    $this->assertFalse((bool) $this->config('language.config_type_settings')->get('show_langcode.view'));

    $this->assertLangcodeFieldVisible(FALSE);
  }

  /**
   * Asserts langcode visibility across supported config entity forms.
   *
   * @param bool $visible
   *   TRUE when the selector should be visible, FALSE otherwise.
   */
  protected function assertLangcodeFieldVisible(bool $visible): void {
    foreach ($this->getConfigEntityFormPaths() as $path) {
      $this->drupalGet($path);
      if ($visible) {
        $this->assertSession()->fieldExists('langcode');
      }
      else {
        $this->assertSession()->fieldNotExists('langcode');
      }
    }
  }

  /**
   * Returns form paths for config entity types covered by this feature.
   *
   * @return string[]
   *   A list of paths.
   */
  protected function getConfigEntityFormPaths(): array {
    return [
      '/admin/structure/menu/add',
      '/admin/config/regional/date-time/formats/add',
      '/admin/structure/taxonomy/add',
      '/admin/structure/views/nojs/edit-details/frontpage/default',
    ];
  }

}
