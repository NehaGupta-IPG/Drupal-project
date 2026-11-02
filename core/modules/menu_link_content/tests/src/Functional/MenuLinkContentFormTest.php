<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_link_content\Functional;

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the menu link content UI.
 */
#[Group('Menu')]
#[RunTestsInSeparateProcesses]
class MenuLinkContentFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_link_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * User with 'administer menu' and 'link to any page' permission.
   *
   * @var \Drupal\user\Entity\User
   */

  protected $adminUser;

  /**
   * User with only 'administer menu' permission.
   *
   * @var \Drupal\user\Entity\User
   */

  protected $basicUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'administer menu',
      'link to any page',
    ]);
    $this->basicUser = $this->drupalCreateUser(['administer menu']);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests the 'link to any page' permission for a restricted page.
   */
  public function testMenuLinkContentFormLinkToAnyPage(): void {
    $menu_link = MenuLinkContent::create([
      'title' => 'Menu link test',
      'provider' => 'menu_link_content',
      'menu_name' => 'admin',
      'link' => ['uri' => 'internal:/user/login'],
    ]);
    $menu_link->save();

    // The user should be able to edit a menu link to the page, even though
    // the user cannot access the page itself.
    $this->drupalGet('/admin/structure/menu/item/' . $menu_link->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);

    // Test that other menus are available when editing existing menu link.
    $this->assertSession()->optionExists('edit-menu-parent', 'main:');

    $this->drupalLogin($this->basicUser);

    $this->drupalGet('/admin/structure/menu/item/' . $menu_link->id() . '/edit');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests the MenuLinkContentForm class.
   */
  public function testMenuLinkContentForm(): void {
    $this->drupalGet('admin/structure/menu/manage/admin/add');
    // Test that other menus are not available when creating a new menu link.
    $this->assertSession()->optionNotExists('edit-menu-parent', 'main:');
    $option = $this->assertSession()->optionExists('edit-menu-parent', 'admin:');
    $this->assertTrue($option->isSelected());
    // Test that the field description is present.
    $this->assertSession()->pageTextContains('Start typing the title of a piece of content to select it. ');

    $this->submitForm([
      'title[0][value]' => 'Front page',
      'link[0][uri]' => '<front>',
    ], 'Save');
    $this->assertSession()->pageTextContains('The menu link has been saved.');
  }

  /**
   * Tests validation for the MenuLinkContentForm class.
   */
  public function testMenuLinkContentFormValidation(): void {
    $this->drupalGet('admin/structure/menu/manage/admin/add');
    $this->submitForm([
      'title[0][value]' => 'Test page',
      'link[0][uri]' => '<test>',
    ], 'Save');
    $this->assertSession()->pageTextContains('Enter a content title to select it, or enter an internal path starting with /, ? or #.');
  }

  /**
   * Tests the operations links alter related functional for menu_link_content.
   */
  public function testMenuLinkContentOperationsLink(): void {
    \Drupal::service('module_installer')->install(['menu_operations_link_test']);
    $menu_link = MenuLinkContent::create([
      'title' => 'Menu link test',
      'provider' => 'menu_link_content',
      'menu_name' => 'main',
      'link' => ['uri' => 'internal:/user/login'],
    ]);
    $menu_link->save();

    // When we are on the listing page, we should be able to see the altered
    // values by alter hook in the operations link menu.
    $this->drupalGet('/admin/structure/menu/manage/main');
    $this->assertSession()->linkExists('Altered Edit Title');
    $this->assertSession()->linkExists('Custom Home');
  }

  /**
   * Tests that absolute URLs pointing at this site are saved as internal.
   */
  public function testReformatInternalLinkIfEnteredAsExternal(): void {
    $base_url = rtrim($this->getAbsoluteUrl('/'), '/');

    // Each case is the string entered in the link field, followed by the URI
    // the menu link is expected to be saved with.
    $cases = [
      // An absolute URL for this site is rewritten as an internal URI.
      [$base_url . '/user/login', 'internal:/user/login'],
      // The query and the fragment are preserved.
      [$base_url . '/user/login?foo=bar#baz', 'internal:/user/login?foo=bar#baz'],
      // The site root is rewritten as the front page.
      [$base_url, 'internal:/'],
      // A host that merely starts with this site's host is left untouched.
      [$base_url . '.example.com/user/login', $base_url . '.example.com/user/login'],
      // Genuinely external URLs are left untouched.
      ['https://www.example.com/user/login', 'https://www.example.com/user/login'],
      // Schemes carrying no host are left untouched, and do not error.
      ['mailto:test@example.com', 'mailto:test@example.com'],
      ['tel:+15551234567', 'tel:+15551234567'],
    ];

    $this->assertLinksSavedAs($cases);
  }

  /**
   * Tests that absolute URLs for a trusted host are saved as internal.
   */
  public function testReformatInternalLinkIfEnteredForATrustedHost(): void {
    // A site can be served from more than one domain, in which case all of
    // them are listed in the trusted host patterns. The host being browsed
    // has to stay among them for the rest of the test to be served.
    $settings['settings']['trusted_host_patterns'] = (object) [
      'value' => [
        '^' . preg_quote(\Drupal::request()->getHost()) . '$',
        '^static\.example\.com$',
      ],
      'required' => TRUE,
    ];
    $this->writeSettings($settings);

    // The other domain serves the same site, so it carries the same base path.
    $base_path = rtrim(parse_url($this->getAbsoluteUrl('/'), PHP_URL_PATH), '/');
    $trusted_base_url = 'http://static.example.com' . $base_path;

    $this->assertLinksSavedAs([
      // A URL for the other domain is rewritten as an internal URI, whether or
      // not the site is being browsed on that domain.
      [$trusted_base_url . '/user/login', 'internal:/user/login'],
      [$trusted_base_url . '/user/login?foo=bar#baz', 'internal:/user/login?foo=bar#baz'],
      // The scheme plays no part in the comparison.
      ['https://static.example.com' . $base_path . '/user/login', 'internal:/user/login'],
      // Hosts are matched case-insensitively.
      ['http://STATIC.example.com' . $base_path . '/user/login', 'internal:/user/login'],
      // The root of the other domain is rewritten as the front page.
      [$trusted_base_url, 'internal:/'],
      // A domain the site is not served from is left untouched, even when it
      // is close to a trusted one.
      ['http://untrusted.example.com/user/login', 'http://untrusted.example.com/user/login'],
      ['http://static.example.com.evil.com/user/login', 'http://static.example.com.evil.com/user/login'],
    ]);
  }

  /**
   * Asserts that entered links are saved with the URI that is expected.
   *
   * @param array $cases
   *   Each case is the string entered in the link field, followed by the URI
   *   the menu link is expected to be saved with.
   */
  protected function assertLinksSavedAs(array $cases): void {
    $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
    foreach ($cases as $delta => [$entered, $expected]) {
      $title = 'Test link ' . $delta;
      $this->drupalGet('admin/structure/menu/manage/admin/add');
      $this->submitForm([
        'title[0][value]' => $title,
        'link[0][uri]' => $entered,
      ], 'Save');

      $links = $storage->loadByProperties(['title' => $title]);
      $link = reset($links);
      $this->assertNotFalse($link, "The menu link entered as '$entered' was saved.");
      $this->assertSame($expected, $link->link->uri, "The link entered as '$entered' was saved with the expected URI.");
    }
  }

}
