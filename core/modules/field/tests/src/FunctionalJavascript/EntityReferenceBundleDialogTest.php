<?php

declare(strict_types=1);

namespace Drupal\Tests\field\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\field_ui\Traits\FieldUiJSTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests bundle creation above an open reference field settings dialog.
 */
#[Group('entity_reference')]
#[RunTestsInSeparateProcesses]
class EntityReferenceBundleDialogTest extends WebDriverTestBase {

  use FieldUiJSTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_ui', 'taxonomy', 'node', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests validation, cancellation, selection, and saving with two dialogs.
   */
  #[DataProvider('fieldProvider')]
  public function testBundleDialog(bool $new_field): void {
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    $this->drupalPlaceBlock('local_actions_block');
    if ($new_field) {
      $this->fieldUIAddNewFieldJS('admin/config/people/accounts', 'dialog', 'Dialog references', 'entity_reference', FALSE);
      $this->getSession()->getPage()->selectFieldOption('field_storage[subform][settings][target_type]', 'taxonomy_term');
      $this->assertSession()->assertWaitOnAjaxRequest();
    }
    else {
      FieldStorageConfig::create([
        'entity_type' => 'user',
        'field_name' => 'field_dialog',
        'type' => 'entity_reference',
        'settings' => ['target_type' => 'taxonomy_term'],
      ])->save();
      FieldConfig::create([
        'entity_type' => 'user',
        'bundle' => 'user',
        'field_name' => 'field_dialog',
        'label' => 'Dialog references',
        'settings' => ['handler_settings' => ['target_bundles' => []]],
      ])->save();

      $this->drupalGet('/admin/config/people/accounts/fields');
      $this->getSession()->resizeWindow(1440, 1200);
      $this->getSession()->getPage()->find('css', 'a[title="Edit field settings."]')->click();
    }
    $assert = $this->assertSession();
    $assert->waitForElementVisible('css', '#drupal-modal .field-config-edit-form');
    $page = $this->getSession()->getPage();
    $field_dialog = $page->find('css', '#drupal-modal');
    $field_dialog->fillField('label', 'Unsaved reference label');
    $field_dialog->checkField('required');
    $field_dialog->checkField('settings[handler_settings][auto_create]');
    $field_dialog->pressButton('Add a new vocabulary.');
    $this->assertNotNull($assert->waitForElementVisible('css', '[id^="drupal-dialog-edit-settings-handler-settings-add-bundle"] form'), $this->getSession()->getPage()->getText());

    // Cancelling only closes the upper dialog and preserves the field draft.
    $page->find('css', '[id^="drupal-dialog-edit-settings-handler-settings-add-bundle"]')->getParent()->pressButton('Close');
    $assert->waitForElementRemoved('css', '[id^="drupal-dialog-edit-settings-handler-settings-add-bundle"]');
    $assert->fieldValueEquals('label', 'Unsaved reference label');
    $assert->checkboxChecked('required');
    $assert->checkboxChecked('settings[handler_settings][auto_create]');
    $field_dialog->pressButton('Add a new vocabulary.');
    $this->assertNotNull($assert->waitForElementVisible('css', '[id^="drupal-dialog-edit-settings-handler-settings-add-bundle"] form'), $this->getSession()->getPage()->getText());

    // Repeated errors replace the bundle form instead of nesting messages.
    for ($attempt = 0; $attempt < 2; $attempt++) {
      $bundle_dialog = $page->find('css', '[id^="drupal-dialog-edit-settings-handler-settings-add-bundle"]');
      $bundle_dialog->getParent()->find('css', '.ui-dialog-buttonpane')->pressButton('Save');
      $assert->assertWaitOnAjaxRequest();
      $assert->elementsCount('css', '[id^="drupal-dialog-edit-settings-handler-settings-add-bundle"] form', 1);
      $assert->elementsCount('css', '[id^="drupal-dialog-edit-settings-handler-settings-add-bundle"] [role="alert"]', 1);
      $assert->elementExists('css', '#drupal-modal .field-config-edit-form');
    }

    $bundle_dialog = $page->find('css', '[id^="drupal-dialog-edit-settings-handler-settings-add-bundle"]');
    $bundle_dialog->fillField('name', 'Dialog tags');
    $assert->waitForText('dialog_tags');
    $bundle_dialog->getParent()->find('css', '.ui-dialog-buttonpane')->pressButton('Save');
    $assert->assertWaitOnAjaxRequest();
    $assert->waitForElementRemoved('css', '[id^="drupal-dialog-edit-settings-handler-settings-add-bundle"]');
    $assert->waitForElementVisible('css', '#drupal-modal input[name="settings[handler_settings][target_bundles][dialog_tags]"]');
    $assert->checkboxChecked('settings[handler_settings][target_bundles][dialog_tags]');
    $assert->fieldValueEquals('label', 'Unsaved reference label');
    $assert->checkboxChecked('required');
    $assert->checkboxChecked('settings[handler_settings][auto_create]');
    $assert->addressEquals('/admin/config/people/accounts/fields');
    if ($new_field) {
      $this->assertNull(FieldConfig::load('user.user.field_dialog'));
    }
    else {
      $this->assertSame([], FieldConfig::load('user.user.field_dialog')->getSetting('handler_settings')['target_bundles']);
    }

    // Saving the original field persists the explicit dialog selection.
    $page->find('css', '#drupal-modal')->getParent()->find('css', '.ui-dialog-buttonpane')->pressButton($new_field ? 'Save' : 'Save settings');
    $this->assertNotEmpty($assert->waitForText('Saved Unsaved reference label configuration.'));
    $page->find('css', 'a[title="Edit field settings."]')->click();
    $assert->waitForElementVisible('css', '#drupal-modal .field-config-edit-form');
    $assert->checkboxChecked('settings[handler_settings][target_bundles][dialog_tags]');
    $assert->fieldValueEquals('label', 'Unsaved reference label');
    $assert->checkboxChecked('required');
    $assert->checkboxChecked('settings[handler_settings][auto_create]');
  }

  /**
   * Provides new and existing field configuration flows.
   */
  public static function fieldProvider(): array {
    return [
      'new field' => [TRUE],
      'existing field' => [FALSE],
    ];
  }

}
