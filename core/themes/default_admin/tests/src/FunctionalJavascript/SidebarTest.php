<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\default_admin\Traits\StickyActionButtonsTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the sidebar panel of content forms.
 *
 * The behavior under test is migration/js/sidebar.js, which is attached to
 * content forms together with the sidebar trigger of the sticky action
 * buttons.
 *
 * All clicks are dispatched as mouse events instead of being sent through the
 * WebDriver, because the sidebar panel is positioned fixed with a higher
 * stacking order than the sticky page header the trigger is rendered in, so it
 * covers the trigger while the panel is open.
 *
 * @see \Drupal\default_admin\Hook\FormHooks::stickyActionButtonsAndSidebar()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
final class SidebarTest extends WebDriverTestBase {

  use StickyActionButtonsTrait;

  /**
   * The viewport width the behavior switches to its mobile branch below.
   *
   * @see core/themes/default_admin/migration/js/sidebar.js
   */
  private const BREAKPOINT = 1024;

  /**
   * The window width used for the desktop branch.
   *
   * The behavior branches on window.innerWidth, which is smaller than the
   * window, so the width is kept well above the breakpoint.
   */
  private const DESKTOP_WIDTH = 1400;

  /**
   * The window width used for the mobile branch.
   */
  private const MOBILE_WIDTH = 800;

  /**
   * The window height used for both branches.
   */
  private const WINDOW_HEIGHT = 900;

  /**
   * The selector of the sidebar trigger of the sticky action buttons.
   *
   * @see \Drupal\default_admin\Hook\FormHooks::stickyActionButtonsAndSidebar()
   */
  private const TRIGGER = '.meta-sidebar__trigger';

  /**
   * The browser storage key of the sidebar state on wide viewports.
   */
  private const DESKTOP_KEY = 'Drupal.defaultAdmin.sidebarExpanded.desktop';

  /**
   * The browser storage key of the sidebar state on narrow viewports.
   */
  private const MOBILE_KEY = 'Drupal.defaultAdmin.sidebarExpanded.mobile';

  /**
   * The browser storage key of the sidebar width.
   */
  private const WIDTH_KEY = 'Drupal.defaultAdmin.sidebarWidth';

  /**
   * The custom property the sidebar width is written to.
   */
  private const WIDTH_PROPERTY = '--admin-theme-sidebar-width';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create page content',
      'edit own page content',
      'administer nodes',
    ]));

    // The sidebar trigger is rendered by the sticky action buttons of content
    // forms, so the feature flag has to be set before the form is requested.
    $this->setStickyActionButtons('content_forms');
  }

  /**
   * Tests the state the sidebar trigger toggles on a wide viewport.
   *
   * Pins the initial open state written by the init routine, the state
   * attribute of the body element, the active state, the expanded state, the
   * title and the label of the trigger, and the browser storage key used above
   * the breakpoint.
   *
   * @see core/themes/default_admin/migration/js/sidebar.js
   */
  public function testTriggerTogglesSidebar(): void {
    $this->loadDesktopForm();

    // With no stored state the init routine creates the desktop key with
    // 'true' and opens the sidebar.
    $this->assertStorage(self::DESKTOP_KEY, 'true');
    $this->assertSidebarState('open');
    $this->assertTriggerState(TRUE);

    // The trigger collapses the sidebar, flips every state it owns and stores
    // the collapsed state under the desktop key.
    $this->dispatchMouseEvent('click', self::TRIGGER);
    $this->assertSidebarState('closed');
    $this->assertTriggerState(FALSE);
    $this->assertStorage(self::DESKTOP_KEY, 'false');

    // Because the trigger is no longer active, the next click opens it again.
    $this->dispatchMouseEvent('click', self::TRIGGER);
    $this->assertSidebarState('open');
    $this->assertTriggerState(TRUE);
    $this->assertStorage(self::DESKTOP_KEY, 'true');

    // The mobile key is never touched above the breakpoint.
    $this->assertStorage(self::MOBILE_KEY, NULL);
  }

  /**
   * Tests the keyboard shortcut of the sidebar.
   *
   * Pins the document level shortcut handler, which toggles the sidebar when
   * the alt key is held down and the physical S key is pressed.
   *
   * @see core/themes/default_admin/migration/js/sidebar.js
   */
  public function testKeyboardShortcutTogglesSidebar(): void {
    $this->loadDesktopForm();
    $this->assertSidebarState('open');

    // The shortcut toggles the same state as the trigger does.
    $this->pressSidebarShortcut();
    $this->assertSidebarState('closed');
    $this->assertTriggerState(FALSE);
    $this->assertStorage(self::DESKTOP_KEY, 'false');

    $this->pressSidebarShortcut();
    $this->assertSidebarState('open');
    $this->assertTriggerState(TRUE);
    $this->assertStorage(self::DESKTOP_KEY, 'true');
  }

  /**
   * Tests that the stored state is restored on a wide viewport.
   *
   * Pins the branch of the init routine that reads the desktop key, for both
   * of its values.
   *
   * @see core/themes/default_admin/migration/js/sidebar.js
   */
  public function testStoredStateIsRestored(): void {
    // A stored collapsed state survives the page load.
    $this->loadDesktopForm([self::DESKTOP_KEY => 'false']);
    $this->assertSidebarState('closed');
    $this->assertTriggerState(FALSE);
    $this->assertStorage(self::DESKTOP_KEY, 'false');

    // A stored open state opens the sidebar.
    $this->loadDesktopForm([self::DESKTOP_KEY => 'true']);
    $this->assertSidebarState('open');
    $this->assertTriggerState(TRUE);
    $this->assertStorage(self::DESKTOP_KEY, 'true');
  }

  /**
   * Tests the browser storage key used on a narrow viewport.
   *
   * Pins that the init routine skips the state of the desktop key below the
   * breakpoint while still creating it, that the resize handler collapses the
   * sidebar, and that both the trigger and the overlay write the mobile key
   * and leave the desktop key alone.
   *
   * @see core/themes/default_admin/migration/js/sidebar.js
   */
  public function testMobileViewportUsesMobileStorageKey(): void {
    $this->loadMobileForm();

    // The desktop key is created regardless of the viewport, but its value is
    // only applied above the breakpoint. The resize handler runs for the
    // initial observation of the html element and collapses the sidebar, which
    // writes the mobile key.
    $this->assertStorage(self::DESKTOP_KEY, 'true');
    $this->assertStorage(self::MOBILE_KEY, 'false');
    $this->assertSidebarState('closed');

    // Opening the sidebar writes the mobile key only.
    $this->dispatchMouseEvent('click', self::TRIGGER);
    $this->assertSidebarState('open');
    $this->assertStorage(self::MOBILE_KEY, 'true');
    $this->assertStorage(self::DESKTOP_KEY, 'true');

    // The overlay, which is only usable below the breakpoint, collapses the
    // sidebar again through its own handler.
    $this->dispatchMouseEvent('click', '.meta-sidebar__overlay');
    $this->assertSidebarState('closed');
    $this->assertStorage(self::MOBILE_KEY, 'false');
    $this->assertStorage(self::DESKTOP_KEY, 'true');
  }

  /**
   * Tests resizing the sidebar with its drag handle.
   *
   * Pins that the drag handle writes the sidebar width to the custom property
   * of the document element, that the width is clamped to the documented
   * bounds, that the width is stored when the drag ends, and that moving the
   * pointer without holding the handle does not change the width.
   *
   * @see core/themes/default_admin/migration/js/sidebar.js
   */
  public function testSidebarWidthIsResizedAndStored(): void {
    $this->loadDesktopForm();
    $this->assertSidebarState('open');
    $this->assertSession()->elementExists('css', '#sidebar-draggable');

    // The handler subtracts the horizontal movement from the width the panel
    // had when the drag started, so moving towards the start of the line grows
    // the panel until it is clamped at its maximum.
    $this->dispatchMouseEvent('mousedown', '#sidebar-draggable', 600);
    $this->dispatchMouseEvent('mousemove', 'html', 0);
    $this->assertSidebarWidth('560px');

    // Moving back past the other end clamps the width at its minimum.
    $this->dispatchMouseEvent('mousemove', 'html', 2000);
    $this->assertSidebarWidth('240px');

    // The width is only stored when the drag ends.
    $this->assertStorage(self::WIDTH_KEY, NULL);
    $this->dispatchMouseEvent('mouseup', 'html', 2000);
    $this->assertStorage(self::WIDTH_KEY, '240px');

    // Once the drag has ended, further movement is ignored.
    $this->dispatchMouseEvent('mousemove', 'html', 0);
    $this->assertSidebarWidth('240px');
  }

  /**
   * Loads the content form with a viewport above the breakpoint.
   *
   * @param array<string, string> $storage
   *   (optional) Browser storage values to prepare for the page load, keyed by
   *   storage key.
   */
  private function loadDesktopForm(array $storage = []): void {
    $inner_width = $this->loadContentForm(self::DESKTOP_WIDTH, $storage);
    $this->assertGreaterThanOrEqual(self::BREAKPOINT, $inner_width, 'The sidebar behavior only takes its desktop branch above the breakpoint.');
  }

  /**
   * Loads the content form with a viewport below the breakpoint.
   *
   * @param array<string, string> $storage
   *   (optional) Browser storage values to prepare for the page load, keyed by
   *   storage key.
   */
  private function loadMobileForm(array $storage = []): void {
    $inner_width = $this->loadContentForm(self::MOBILE_WIDTH, $storage);
    $this->assertLessThan(self::BREAKPOINT, $inner_width, 'The sidebar behavior only takes its mobile branch below the breakpoint.');
  }

  /**
   * Loads the content form with a prepared browser storage.
   *
   * The form is requested twice, because browser storage can only be written
   * once a page of the site is open, and the behavior reads it while it is
   * attaching.
   *
   * @param int $width
   *   The window width to set before the form is requested.
   * @param array<string, string> $storage
   *   Browser storage values to prepare for the page load, keyed by storage
   *   key. The storage is always cleared first.
   *
   * @return int
   *   The inner width of the window the form was loaded with.
   */
  private function loadContentForm(int $width, array $storage): int {
    $session = $this->getSession();
    $session->resizeWindow($width, self::WINDOW_HEIGHT);
    $this->drupalGet('node/add/page');

    $script = 'localStorage.clear();';
    foreach ($storage as $key => $value) {
      $script .= sprintf(
        'localStorage.setItem(%s, %s);',
        json_encode($key, JSON_THROW_ON_ERROR),
        json_encode($value, JSON_THROW_ON_ERROR),
      );
    }
    $session->executeScript($script);
    $session->reload();
    $this->assertSession()->elementExists('css', self::TRIGGER);

    // The window is not the viewport, and the requested size may have been
    // clamped, so the branch the assertions rely on is measured.
    return (int) $session->evaluateScript('window.innerWidth');
  }

  /**
   * Asserts the state the sidebar exposes on the body element.
   *
   * @param string $state
   *   The expected state, either "open" or "closed".
   */
  private function assertSidebarState(string $state): void {
    $this->assertJsCondition(sprintf(
      'document.body.getAttribute("data-meta-sidebar") === %s',
      json_encode($state, JSON_THROW_ON_ERROR),
    ));
  }

  /**
   * Asserts the state of the sidebar trigger.
   *
   * @param bool $expanded
   *   TRUE if the trigger is expected to advertise an open sidebar, FALSE if
   *   it is expected to advertise a collapsed one.
   */
  private function assertTriggerState(bool $expanded): void {
    $label = $expanded ? 'Hide sidebar panel' : 'Show sidebar panel';
    $selector = sprintf(
      '%s%s[aria-expanded="%s"][title="%s"]',
      self::TRIGGER,
      $expanded ? '.is-active' : ':not(.is-active)',
      $expanded ? 'true' : 'false',
      $label,
    );
    $this->assertNotNull($this->assertSession()->waitForElement('css', $selector), sprintf('The sidebar trigger advertises the label "%s".', $label));

    // The accessible label of the trigger carries the same string.
    $this->assertJsCondition(sprintf(
      'document.querySelector("%s span").textContent === %s',
      self::TRIGGER,
      json_encode($label, JSON_THROW_ON_ERROR),
    ));
  }

  /**
   * Asserts the sidebar width written to the document element.
   *
   * @param string $width
   *   The expected value of the custom property.
   */
  private function assertSidebarWidth(string $width): void {
    $this->assertJsCondition(sprintf(
      'document.documentElement.style.getPropertyValue(%s) === %s',
      json_encode(self::WIDTH_PROPERTY, JSON_THROW_ON_ERROR),
      json_encode($width, JSON_THROW_ON_ERROR),
    ));
  }

  /**
   * Asserts a browser storage value.
   *
   * @param string $key
   *   The storage key to read.
   * @param string|null $value
   *   The expected value, or NULL if the key is expected to be absent.
   */
  private function assertStorage(string $key, ?string $value): void {
    $this->assertJsCondition(sprintf(
      'localStorage.getItem(%s) === %s',
      json_encode($key, JSON_THROW_ON_ERROR),
      json_encode($value, JSON_THROW_ON_ERROR),
    ));
  }

  /**
   * Dispatches a mouse event on an element.
   *
   * @param string $type
   *   The event type to dispatch.
   * @param string $selector
   *   The CSS selector of the element to dispatch the event on. The event
   *   bubbles, so "html" reaches the handlers bound to the document.
   * @param int $client_x
   *   (optional) The horizontal coordinate of the event, which the resize
   *   handlers read. Defaults to 0.
   */
  private function dispatchMouseEvent(string $type, string $selector, int $client_x = 0): void {
    $encoded_type = json_encode($type, JSON_THROW_ON_ERROR);
    $encoded_selector = json_encode($selector, JSON_THROW_ON_ERROR);
    $this->getSession()->executeScript(<<<JS
      document.querySelector($encoded_selector).dispatchEvent(
        new MouseEvent($encoded_type, {
          bubbles: true,
          cancelable: true,
          clientX: $client_x,
        }),
      );
      JS);
  }

  /**
   * Presses the keyboard shortcut of the sidebar.
   *
   * The shortcut handler reads the physical key of the event, which the
   * synthetic events of the Mink driver do not provide, so a real keyboard
   * event is dispatched on the document the handler is bound to.
   */
  private function pressSidebarShortcut(): void {
    $this->getSession()->executeScript(<<<'JS'
      document.dispatchEvent(
        new KeyboardEvent('keydown', {
          bubbles: true,
          cancelable: true,
          altKey: true,
          code: 'KeyS',
          key: 's',
        }),
      );
      JS);
  }

}
