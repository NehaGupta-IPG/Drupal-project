<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\FunctionalJavascript;

use Drupal\comment\Entity\CommentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\field_ui\Traits\FieldUiJSTestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests comment type creation above an open field settings dialog.
 */
#[Group('comment')]
#[RunTestsInSeparateProcesses]
class CommentTypeDialogTest extends WebDriverTestBase {

  use FieldUiJSTestTrait;
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_ui', 'comment', 'media', 'node', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests validation, cancellation, target matching, and selecting a new type.
   */
  public function testCommentTypeDialog(): void {
    $this->createMediaType('image', ['id' => 'image']);
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    $this->drupalPlaceBlock('local_actions_block');
    $this->fieldUIAddNewFieldJS('admin/structure/media/manage/image', 'dialog_comment', 'Dialog comments', 'comment', FALSE);
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();
    $selector = '[id^="drupal-dialog-edit-field-storage-subform-settings-add-comment-type"]';
    $field_dialog = $page->find('css', '#drupal-modal');
    $field_dialog->fillField('label', 'Unsaved comment label');
    $field_dialog->pressButton('Add a new comment type.');
    $this->assertNotNull($assert->waitForElementVisible('css', $selector . ' form'));
    $assert->fieldValueEquals('target_entity_type_id', 'media');

    // Cancelling only closes the upper dialog and preserves the field draft.
    $page->find('css', $selector)->getParent()->pressButton('Close');
    $assert->waitForElementRemoved('css', $selector);
    $assert->fieldValueEquals('label', 'Unsaved comment label');
    $field_dialog->pressButton('Add a new comment type.');
    $this->assertNotNull($assert->waitForElementVisible('css', $selector . ' form'));

    // Validation errors replace the child form without closing the caller.
    for ($attempt = 0; $attempt < 2; $attempt++) {
      $page->find('css', $selector)->getParent()->find('css', '.ui-dialog-buttonpane')->pressButton('Save and manage fields');
      $assert->assertWaitOnAjaxRequest();
      $assert->elementsCount('css', $selector . ' form', 1);
      $assert->elementsCount('css', $selector . ' [role="alert"]', 1);
      $assert->elementExists('css', '#drupal-modal .field-config-edit-form');
    }

    // A type deliberately created for another target must not be selected.
    $dialog = $page->find('css', $selector);
    $dialog->fillField('label', 'Other comments');
    $assert->waitForText('other_comments');
    $dialog->selectFieldOption('target_entity_type_id', 'node');
    $dialog->getParent()->find('css', '.ui-dialog-buttonpane')->pressButton('Save and manage fields');
    $assert->assertWaitOnAjaxRequest();
    $assert->waitForElementRemoved('css', $selector);
    $assert->fieldNotExists('field_storage[subform][settings][comment_type]');
    $assert->fieldValueEquals('label', 'Unsaved comment label');
    $page->find('css', '#drupal-modal')->pressButton('Add a new comment type.');
    $this->assertNotNull($assert->waitForElementVisible('css', $selector . ' form'));
    $assert->fieldValueEquals('target_entity_type_id', 'media');

    $dialog = $page->find('css', $selector);
    $dialog->fillField('label', 'Dialog comments');
    $assert->waitForText('dialog_comments');
    $dialog->getParent()->find('css', '.ui-dialog-buttonpane')->pressButton('Save and manage fields');
    $assert->assertWaitOnAjaxRequest();
    $assert->waitForElementRemoved('css', $selector);
    $assert->fieldValueEquals('field_storage[subform][settings][comment_type]', 'dialog_comments');
    $assert->fieldValueEquals('label', 'Unsaved comment label');
    $assert->addressEquals('/admin/structure/media/manage/image/fields');
    $this->assertSame('media', CommentType::load('dialog_comments')->getTargetEntityTypeId());
    $this->assertNull(FieldConfig::load('media.image.field_dialog_comment'));

    // Only saving the calling form persists the new field and its selection.
    $page->find('css', '#drupal-modal')->getParent()->find('css', '.ui-dialog-buttonpane')->pressButton('Save');
    $this->assertNotEmpty($assert->waitForText('Saved Unsaved comment label configuration.'));
    $storage = FieldStorageConfig::loadByName('media', 'field_dialog_comment');
    $this->assertSame('dialog_comments', $storage->getSetting('comment_type'));
  }

}
