<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\default_admin\Traits\StickyActionButtonsTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the sticky action buttons relay.
 *
 * The buttons of the sticky page header are copies of the real action buttons
 * and they live outside the form they belong to.
 * Drupal.adminStickyFormActions binds them back to that form: it points them at
 * the form id of the rendered form and forwards a click on a copy to the
 * original button inside the form. It also shuttles keyboard focus between the
 * end of the form and the sticky header.
 *
 * The server side markup these behaviors operate on is covered by
 * \Drupal\Tests\default_admin\Functional\NodeFormTest.
 *
 * @see \Drupal\default_admin\Hook\FormHooks::stickyActionButtonsAndSidebar()
 * @see \Drupal\default_admin\Hook\FormHooks::formAfterBuild()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
final class StickyFormActionsTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use StickyActionButtonsTrait;

  /**
   * The selector of the action buttons printed in the sticky page header.
   *
   * The real action container of the form carries the same class, so the
   * header has to be part of the selector to tell the two apart.
   *
   * @see \Drupal\default_admin\Hook\FormHooks::stickyActionButtonsAndSidebar()
   */
  private const STICKY_BAR = 'header.region-sticky .sticky-form-actions';

  /**
   * The selector of the node form once it has been marked as sticky.
   */
  private const NODE_FORM = 'form.node-edit-form.has-sticky-form-actions';

  /**
   * The name of the field whose edit form has an Ajax action button.
   */
  private const FIELD_NAME = 'field_subtitle';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'field_ui', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    // A plain field, because its edit form is the simplest form in core whose
    // save button carries Ajax.
    FieldStorageConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Subtitle',
    ])->save();

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create page content',
      'administer node fields',
    ]));
  }

  /**
   * Tests that the copied buttons are bound to the rendered form.
   *
   * @see \Drupal\default_admin\Hook\FormHooks::formAfterBuild()
   */
  public function testCopiedButtonsReferenceTheRealForm(): void {
    $this->setStickyActionButtons('content_forms');
    $form = $this->openNodeAddForm();
    $assert_session = $this->assertSession();

    $form_id = $form->getAttribute('id');
    $this->assertNotEmpty($form_id);

    foreach (['edit-submit', 'edit-preview'] as $selector) {
      // The behavior marks every button of the action container it has taken
      // care of.
      $original = $assert_session->elementExists('css', self::NODE_FORM . ' [data-drupal-selector="' . $selector . '"][data-once~="adminSyncActionButtons"]');

      // The copy is told which form it submits and which button it stands in
      // for, both read from the rendered document rather than from the render
      // array that produced it.
      $copy = $assert_session->elementExists('css', self::STICKY_BAR . ' [data-drupal-selector="sticky-' . $selector . '"]');
      $this->assertSame($form_id, $copy->getAttribute('form'));
      $this->assertSame($original->getAttribute('id'), $copy->getAttribute('data-sticky-form-selector'));
    }
  }

  /**
   * Tests that the copied save button saves the node.
   */
  public function testCopiedSaveSubmitsTheForm(): void {
    $this->setStickyActionButtons('content_forms');
    $this->openNodeAddForm();
    $assert_session = $this->assertSession();

    $title = 'Saved from the sticky bar';
    $this->getSession()->getPage()->fillField('title[0][value]', $title);
    $assert_session->elementExists('css', self::STICKY_BAR . ' [data-drupal-selector="sticky-edit-submit"]')->click();

    // The click ends up in the submit handler of the real form, so the node is
    // created and the browser is taken where a regular save would take it.
    $this->assertTrue($assert_session->waitForText('Basic page ' . $title . ' has been created.'));
    $node = $this->getNodeByTitle($title, TRUE);
    $this->assertNotNull($node);
    $assert_session->addressEquals('node/' . $node->id());
  }

  /**
   * Tests that the copied preview button previews the node.
   *
   * @see \Drupal\node\Form\NodeForm::preview()
   */
  public function testCopiedPreviewSubmitsTheForm(): void {
    $this->setStickyActionButtons('content_forms');
    $this->openNodeAddForm();
    $assert_session = $this->assertSession();

    $title = 'Previewed from the sticky bar';
    $this->getSession()->getPage()->fillField('title[0][value]', $title);
    $assert_session->elementExists('css', self::STICKY_BAR . ' [data-drupal-selector="sticky-edit-preview"]')->click();

    // Preview is a second non-Ajax action button, and it reaches its own
    // submit handler, which redirects to the preview of the unsaved node.
    $this->assertNotNull($assert_session->waitForLink('Back to content editing'));
    $this->assertStringContainsString('/node/preview/', $this->getSession()->getCurrentUrl());
    $assert_session->pageTextContains($title);
  }

  /**
   * Tests that a button carrying Ajax is triggered with a mouse down event.
   *
   * Ajax submit buttons are bound to their mouse down event instead of their
   * click event, so forwarding the click alone would be swallowed.
   *
   * @see \Drupal\Core\Render\Element\RenderElementBase::preRenderAjaxForm()
   * @see \Drupal\field_ui\Form\FieldConfigEditForm::actions()
   */
  public function testCopiedAjaxButtonIsTriggeredWithMouseDown(): void {
    // The field edit form is not a content form, so it only takes part in the
    // sticky action buttons when they are enabled for every form.
    $this->setStickyActionButtons('always');
    $assert_session = $this->assertSession();

    $this->getSession()->resizeWindow(1400, 1000);
    $this->drupalGet('admin/structure/types/manage/page/fields/node.page.' . self::FIELD_NAME);
    $this->assertNotNull($assert_session->waitForElement('css', 'form.has-sticky-form-actions[data-once~="adminEditForm"]'));

    // The Ajax mark of the original button is the flag the behavior reads to
    // decide whether a mouse down event is needed.
    $original = 'form.has-sticky-form-actions [data-drupal-selector="edit-submit"]';
    $assert_session->elementExists('css', $original . '[data-once~="drupal-ajax"]');

    $this->getSession()->getPage()->fillField('label', 'Sticky bar label');

    // Both events are dispatched synchronously while the copy is clicked, so
    // the recorded order is complete as soon as the click returns.
    $copy = self::STICKY_BAR . ' [data-drupal-selector="sticky-edit-submit"]';
    $recorded = $this->getSession()->evaluateScript(<<<JS
      (() => {
        const button = document.querySelector('$original');
        const events = [];
        button.addEventListener('mousedown', () => events.push('mousedown'));
        button.addEventListener('click', () => events.push('click'));
        document.querySelector('$copy').click();
        return events.join(',');
      })()
      JS);
    $this->assertSame('mousedown,click', $recorded);

    // The relayed click submits the form for real.
    $this->assertTrue($assert_session->waitForText('Saved Sticky bar label configuration.'));
    $this->assertStringContainsString('/admin/structure/types/manage/page/fields', $this->getSession()->getCurrentUrl());
  }

  /**
   * Tests the focus shuttle between the end of the form and the sticky bar.
   */
  public function testFocusMovesBetweenFormAndStickyBar(): void {
    $this->setStickyActionButtons('content_forms');
    $this->openNodeAddForm();
    $assert_session = $this->assertSession();
    $session = $this->getSession();

    $skip_link = '[data-move-focus-to-sticky-bar]';
    $this->assertNotNull($assert_session->waitForElement('css', $skip_link . '[data-once~="adminMoveFocusToStickyBar"]'));

    // The skip link is the last element of the form, and focusing it hands
    // focus to the first control of the sticky bar and appends the link that
    // hands focus back.
    $session->executeScript(sprintf('document.querySelector("%s").focus();', $skip_link));
    $this->assertNotNull($assert_session->waitForElement('css', self::STICKY_BAR . ' a[data-move-focus-to-end-of-form]'));
    $this->assertJsCondition(sprintf("document.querySelector('%s').contains(document.activeElement)", self::STICKY_BAR));

    // Focusing that link removes the element it was added in and returns focus
    // to the form, which is outside of the sticky bar.
    $back_link = '[data-move-focus-to-end-of-form]';
    $session->executeScript(sprintf('document.querySelector("%s").focus();', $back_link));
    $this->assertTrue($assert_session->waitForElementRemoved('css', $back_link));
    $this->assertJsCondition(sprintf("!document.querySelector('%s').contains(document.activeElement)", self::STICKY_BAR));
    $this->assertJsCondition(sprintf("document.querySelector('%s').contains(document.activeElement)", self::NODE_FORM));
  }

  /**
   * Opens the node form and waits for the sticky action buttons to be bound.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The form element the behavior has processed.
   */
  private function openNodeAddForm(): NodeElement {
    // The window size decides which layout the sidebar of a content form
    // builds, so it is pinned instead of relying on the default.
    $this->getSession()->resizeWindow(1400, 1000);
    $this->drupalGet('node/add/page');

    $form = $this->assertSession()->waitForElement('css', self::NODE_FORM . '[data-once~="adminEditForm"]');
    $this->assertNotNull($form);

    return $form;
  }

}
