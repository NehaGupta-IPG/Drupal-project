<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Functional;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the markup Default Admin adds to the administrative listing pages.
 *
 * @see \Drupal\default_admin\Hook\FormHooks::formMenuLinkContentFormAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::formSystemModulesAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::formUserAdminPermissionsAlter()
 * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessAdminBlock()
 * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessAdminBlockContent()
 * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessLinks()
 * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessLinksActionLinks()
 * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessNodeAddList()
 * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessSystemThemesPage()
 * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessTable()
 * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessViewsViewTable()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class AdminPagesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_content',
    'dblog',
    'help',
    'menu_link_content',
    'menu_ui',
    'node',
    'views',
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

    $this->assertTrue(\Drupal::service('theme_installer')->install(['default_admin']));
    $this->container->get('config.factory')
      ->getEditable('system.theme')
      ->set('default', 'default_admin')
      ->set('admin', 'default_admin')
      ->save();

    // Two content types are needed, otherwise node/add redirects straight to
    // the single add form instead of rendering the list. Only one of them gets
    // a description so that both branches of the description markup are
    // covered.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
      'description' => 'Use for static content.',
    ]);
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // The content view only renders its table when there is at least one row.
    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Published test page',
      'status' => 1,
    ]);

    // Same reasoning as for the content types: block/add redirects when only a
    // single block type exists.
    BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic block',
      'revision' => FALSE,
      'description' => 'A reusable block of content.',
    ])->save();
    BlockContentType::create([
      'id' => 'banner',
      'label' => 'Banner block',
      'revision' => FALSE,
    ])->save();

    // The menu overview form only builds a table when the menu has links.
    MenuLinkContent::create([
      'title' => 'Test menu link',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'main',
    ])->save();

    $account = $this->drupalCreateUser([
      'access administration pages',
      'access content overview',
      'access site reports',
      'administer block content',
      'administer blocks',
      'administer menu',
      'administer modules',
      'administer permissions',
      'administer site configuration',
      'administer themes',
      'create article content',
      'create page content',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Tests the content type list rendered by the entity add list template.
   */
  public function testNodeAddList(): void {
    $this->drupalGet('node/add');
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);

    // preprocessNodeAddList() adds node-type-list to the list attributes, on
    // top of the classes entity-add-list.html.twig sets itself.
    $assert_session->elementExists('css', '.panel__content.admin-list.node-type-list');

    // Every bundle is rendered as a stretched link pointing at its add form.
    $assert_session->elementsCount('css', '.node-type-list .admin-item > a.admin-item__link', 2);
    $assert_session->elementExists('css', '.node-type-list a.admin-item__link[href$="/node/add/article"]');
    $assert_session->elementExists('css', '.node-type-list a.admin-item__link[href$="/node/add/page"]');

    // The visible label lives next to the link, not inside it.
    $assert_session->elementTextEquals('css', '.node-type-list a.admin-item__link[href$="/node/add/page"] + .admin-item__title', 'Basic page');

    // A bundle with a description gets a description element that its title
    // element refers to, a bundle without one gets neither.
    $assert_session->elementExists('css', '.admin-item__title[aria-details="basic-page-desc"]');
    $assert_session->elementTextEquals('css', '#basic-page-desc.admin-item__description', 'Use for static content.');
    $assert_session->elementNotExists('css', '.admin-item__title[aria-details="article-desc"]');
    $assert_session->elementNotExists('css', '#article-desc');
  }

  /**
   * Tests the block type list keeps the query parameters on its add links.
   */
  public function testBlockContentAddList(): void {
    $this->drupalGet('block/add', [
      'query' => [
        'theme' => 'stark',
        'region' => 'content',
      ],
    ]);
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);

    $assert_session->elementsCount('css', '.admin-list .admin-item > a.admin-item__link', 2);

    // entity-add-list.html.twig rebuilds the anchor from the link URL, so the
    // query parameters that place the new block have to survive that.
    $assert_session->elementExists('css', 'a.admin-item__link[href*="/block/add/basic"][href*="theme=stark"][href*="region=content"]');
    $assert_session->elementExists('css', 'a.admin-item__link[href*="/block/add/banner"][href*="theme=stark"][href*="region=content"]');

    // A block type with a description gets a description element that its
    // title element refers to, a block type without one gets neither.
    $assert_session->elementExists('css', '.admin-item__title[aria-details="basic-block-desc"]');
    $assert_session->elementTextEquals('css', '#basic-block-desc.admin-item__description', 'A reusable block of content.');
    $assert_session->elementNotExists('css', '#banner-block-desc');
  }

  /**
   * Tests the panels and item links on an administration overview page.
   */
  public function testAdminPageBlocks(): void {
    $this->drupalGet('admin/config');
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);

    // admin-page.html.twig lays the panels out in two columns.
    $assert_session->elementExists('css', '.layout-row.clearfix > .layout-column.layout-column--half');

    // admin-block.html.twig wraps every block in a titled panel.
    $assert_session->elementExists('css', '.panel.layer-wrapper > .panel__title');

    // preprocessAdminBlock() adds admin-list--panel to the list of a block
    // that has content, through the attributes variable that
    // themeRegistryAlter() registers for admin_block_content.
    $assert_session->elementExists('css', '.panel__content > .admin-list.admin-list--panel.layer-wrapper');

    // preprocessAdminBlockContent() rebuilds every item link with the
    // admin-item__link class on the anchor itself.
    $assert_session->elementExists('css', '.admin-list .admin-item > a.admin-item__link[href$="/admin/config/system/site-information"]');

    // Item descriptions are kept and referenced from the item title.
    $assert_session->elementExists('css', '.admin-item__title[aria-details="basic-site-settings-desc"]');
    $assert_session->elementTextContains('css', '#basic-site-settings-desc.admin-item__description', 'Change site name');
  }

  /**
   * Tests the operation links and labeling of the theme cards.
   *
   * The action link assertions below depend on preprocessLinks() iterating
   * over the links by reference; with a by-value loop the converted links are
   * discarded and the operations render as plain links.
   */
  public function testAppearancePageActionLinks(): void {
    $this->drupalGet('admin/appearance');
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);

    // preprocessSystemThemesPage() re-themes the operations as action links,
    // which is what turns the list into an action links list.
    $assert_session->elementExists('css', '.system-themes-list--installed ul.operations.action-links > li.action-links__item');

    // The default theme only offers a settings operation.
    $assert_session->elementExists('css', 'ul.action-links a.action-link.action-link--small.action-link--icon-cog[href$="/admin/appearance/settings/default_admin"]');

    // An installed theme that is not the default can be uninstalled and made
    // the default.
    $assert_session->elementExists('css', 'ul.action-links a.action-link.action-link--small.action-link--icon-ex[href*="/admin/appearance/uninstall"][href*="theme=stark"]');
    $assert_session->elementExists('css', 'ul.action-links a.action-link.action-link--small.action-link--icon-checkmark[href*="/admin/appearance/default"][href*="theme=stark"]');

    // An uninstalled theme offers the install operation.
    $assert_session->elementExists('css', '.system-themes-list--uninstalled ul.action-links a.action-link.action-link--small.action-link--icon-plus[href*="/admin/appearance/install"][href*="theme=olivero"]');

    // preprocessSystemThemesPage() generates the identifiers that connect a
    // theme card to its own title and description.
    $card = $assert_session->elementExists('css', '.system-themes-list--installed .card.theme-default');
    $title_id = $card->getAttribute('aria-labelledby');
    $this->assertIsString($title_id);
    $this->assertNotEmpty($title_id);
    $assert_session->elementTextContains('css', 'h3#' . $title_id, 'Default Admin');

    $description_id = $card->getAttribute('aria-describedby');
    $this->assertIsString($description_id);
    $this->assertNotEmpty($description_id);
    $assert_session->elementExists('css', 'div#' . $description_id . '.card__content-item');

    // A theme that ships a screenshot keeps it.
    $assert_session->elementExists('css', '.card.theme-default .card__image img');
  }

  /**
   * Tests the counters and grouped requirements of the status report.
   */
  public function testStatusReportPage(): void {
    $this->drupalGet('admin/reports/status');
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);

    // status-report-page.html.twig puts every counter in its own card and
    // status-report-counter.html.twig renders the counter itself.
    $assert_session->elementExists('css', '.system-status-report-counters > .card.system-status-report-counters__item > .system-status-counter.system-status-counter--checked');

    // The counter links to the matching group of the grouped report.
    $assert_session->elementExists('css', '.system-status-counter--checked .system-status-counter__details a[href="#checked"]');
    $assert_session->elementExists('css', '.system-status-report.layer-wrapper details.details > summary#checked.details__summary--system-status-report');
    $assert_session->elementExists('css', '.details__wrapper--system-status-report > .system-status-report__row > .system-status-report__status-title');

    // status-report-general-info.html.twig renders the system information.
    $assert_session->elementExists('css', '.system-status-general-info.layer-wrapper > h2.system-status-general-info__header');
    $assert_session->elementExists('css', '.system-status-general-info__items > .system-status-general-info__item.card .system-status-general-info__item-icon--drupal');
  }

  /**
   * Tests the filter and the operation links of the modules page.
   */
  public function testModulesPage(): void {
    $this->drupalGet('admin/modules');
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);

    // formSystemModulesAlter() tags the filter container and replaces the
    // invisible title with a visible one.
    $assert_session->elementExists('css', '.table-filter.modules-table-filter');
    $assert_session->elementTextEquals('css', '.modules-table-filter label', 'Filter');
    $assert_session->elementNotExists('css', '.modules-table-filter label.visually-hidden');

    // Every known module operation becomes a small action link with its own
    // icon, rendered by system-modules-details.html.twig.
    $assert_session->elementExists('css', '.module-details__links a.action-link.action-link--small.action-link--icon-questionmark[href$="/admin/help/node"]');
    $assert_session->elementExists('css', '.module-details__links a.action-link.action-link--small.action-link--icon-key[href$="/admin/people/permissions/module/node"]');
    $assert_session->elementExists('css', '.module-details__links a.action-link.action-link--small.action-link--icon-cog[href$="/admin/config/development/logging"]');

    // The classes the form itself sets are kept.
    $assert_session->elementExists('css', '.module-details__links a.action-link.module-link.module-link-permissions');
  }

  /**
   * Tests the filter of the permissions page.
   */
  public function testPermissionsPage(): void {
    $this->drupalGet('admin/people/permissions');
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);

    // formUserAdminPermissionsAlter() tags the filter container and replaces
    // the invisible title with a visible one.
    $assert_session->elementExists('css', '.table-filter.permissions-table-filter');
    $assert_session->elementTextEquals('css', '.permissions-table-filter label', 'Filter');
    $assert_session->elementNotExists('css', '.permissions-table-filter label.visually-hidden');
  }

  /**
   * Tests the two column layout of the menu link form.
   */
  public function testMenuLinkContentFormLayout(): void {
    $this->drupalGet('admin/structure/menu/manage/main/add');
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);

    // formMenuLinkContentFormAlter() sets menu_link_form as the form theme,
    // which renders the form through form-two-columns.html.twig.
    $main = $assert_session->elementExists('css', '.layout-form.clearfix > .layout-region.layout-region--main > .layout-region__content');
    $assert_session->elementExists('css', '.layout-form.clearfix > .layout-region.layout-region--secondary#default_admin_sidebar > .layout-region__content');
    $footer = $assert_session->elementExists('css', '.layout-form.clearfix > .layout-region.layout-region--footer > .layout-region__content');

    // The plain form elements stay in the main column, the actions move to the
    // footer.
    $this->assertNotNull($main->findField('Menu link title'));
    $this->assertNotNull($footer->findButton('Save'));

    // The parent selector is moved into an accordion in the sidebar.
    $assert_session->elementExists('css', '.layout-region--secondary .entity-meta .accordion select.menu-title-select');

    // Weight and expanded are grouped in the display settings details.
    $display_settings = $assert_session->elementExists('css', '.layout-region--secondary .entity-meta details.entity-meta__options');
    $this->assertNotNull($display_settings->findField('Weight'));
    $this->assertNotNull($display_settings->findField('Show as expanded'));
    $this->assertNull($main->findField('Weight'));
    $this->assertNull($main->findField('Show as expanded'));

    // The description gets a details element of its own.
    $description = $assert_session->elementExists('css', '.layout-region--secondary .entity-meta details.entity-meta__description');
    $this->assertNotNull($description->findField('Description'));
    $this->assertNull($main->findField('Description'));
  }

  /**
   * Tests the sortable and draggable table markup.
   */
  public function testDraggableAndSortableTable(): void {
    $assert_session = $this->assertSession();

    // preprocessTable() marks the header cells of a plain table whose sort
    // link carries both an order and a sort parameter. table.html.twig gives
    // the linked headers th__item and the plain ones a label based class.
    $this->drupalGet('admin/reports/dblog');
    $assert_session->statusCodeEquals(200);
    $assert_session->elementExists('css', 'table#admin-dblog th.th__item.sortable-heading a');
    $assert_session->elementExists('css', 'table#admin-dblog th.th__message');
    $assert_session->elementNotExists('css', 'table#admin-dblog th.th__message.sortable-heading');

    // preprocessViewsViewTable() marks the headers of the views table that
    // link to a sorted version of the same page.
    $this->drupalGet('admin/content');
    $assert_session->statusCodeEquals(200);
    $assert_session->elementExists('css', 'table.views-view-table th.views-field-title.sortable-heading a');
    $assert_session->elementExists('css', 'table.views-view-table th.views-field-changed.sortable-heading a');

    // The bulk form column has no sort link, so it is left alone.
    $assert_session->elementExists('css', 'table.views-view-table th.views-field-node-bulk-form');
    $assert_session->elementNotExists('css', 'table.views-view-table th.views-field-node-bulk-form.sortable-heading');

    // preprocessTable() marks the table and the first cell of every draggable
    // row of the menu overview form.
    $this->drupalGet('admin/structure/menu/manage/main');
    $assert_session->statusCodeEquals(200);
    $assert_session->elementExists('css', 'table#menu-overview.draggable-table');
    $assert_session->elementExists('css', 'table#menu-overview tr.draggable > td.tabledrag-cell');

    // The first cell holds the menu link title, so it is not drag only.
    $assert_session->elementNotExists('css', 'table#menu-overview td.tabledrag-cell--only-drag');
  }

}
