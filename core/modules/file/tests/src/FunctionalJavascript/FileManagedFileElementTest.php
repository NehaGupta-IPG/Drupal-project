<?php

declare(strict_types=1);

namespace Drupal\Tests\file\FunctionalJavascript;

use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\file\Functional\FileFieldCreationTrait;

/**
 * Tests the 'managed_file' element type.
 *
 * @group file
 */
class FileManagedFileElementTest extends WebDriverTestBase {
  use FileFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'file', 'file_module_test', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with administration permissions.
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
      'access content',
      'access administration pages',
      'administer site configuration',
      'administer users',
      'administer permissions',
      'administer content types',
      'administer node fields',
      'administer node display',
      'administer nodes',
      'bypass node access',
    ]);
    $this->drupalLogin($this->adminUser);
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
  }

  /**
   * Tests the managed_file element type.
   */
  public function testManagedFile(): void {
    // Perform the tests with all permutations of $form['#tree'],
    // $element['#extended'], and $element['#multiple'].
    $filename = \Drupal::service('file_system')->tempnam('temporary://', "testManagedFile") . '.txt';
    file_put_contents($filename, $this->randomString(128));
    foreach ([0, 1] as $tree) {
      foreach ([0, 1] as $extended) {
        foreach ([0, 1] as $multiple) {
          $path = 'file/test/' . $tree . '/' . $extended . '/' . $multiple;
          $input_base_name = $tree ? 'nested_file' : 'file';
          $file_field_name = $multiple ? 'files[' . $input_base_name . '][]' : 'files[' . $input_base_name . ']';

          // Now, test the Upload and Remove buttons, with Ajax.
          // Upload, then Submit.
          $last_fid_prior = $this->getLastFileId();
          $this->drupalGet($path);
          $this->getSession()->getPage()->attachFileToField($file_field_name, $this->container->get('file_system')->realpath($filename));
          $uploaded_file = $this->assertSession()->waitForElement('css', '.file--mime-text-plain');
          $this->assertNotEmpty($uploaded_file);
          $last_fid = $this->getLastFileId();
          $this->assertGreaterThan($last_fid_prior, $last_fid, 'New file got uploaded.');
          $this->submitForm([], 'Save');

          // Remove, then Submit.
          $remove_button_title = $multiple ? 'Remove selected' : 'Remove';
          $this->drupalGet($path . '/' . $last_fid);
          if ($multiple) {
            $selected_checkbox = ($tree ? 'nested[file]' : 'file') . '[file_' . $last_fid . '][selected]';
            $this->getSession()->getPage()->checkField($selected_checkbox);
          }
          $this->getSession()->getPage()->pressButton($remove_button_title);
          $this->assertSession()->assertWaitOnAjaxRequest();
          $this->submitForm([], 'Save');
          $this->assertSession()->pageTextContains('The file ids are .');

          // Upload, then Remove, then Submit.
          $this->drupalGet($path);
          $this->getSession()->getPage()->attachFileToField($file_field_name, $this->container->get('file_system')->realpath($filename));
          $uploaded_file = $this->assertSession()->waitForElement('css', '.file--mime-text-plain');
          $this->assertNotEmpty($uploaded_file);
          if ($multiple) {
            $selected_checkbox = ($tree ? 'nested[file]' : 'file') . '[file_' . $this->getLastFileId() . '][selected]';
            $this->getSession()->getPage()->checkField($selected_checkbox);
          }
          $this->getSession()->getPage()->pressButton($remove_button_title);
          $this->assertSession()->assertWaitOnAjaxRequest();

          $this->submitForm([], 'Save');
          $this->assertSession()->pageTextContains('The file ids are .');
        }
      }
    }
  }

  /**
   * Tests the 'absolute_url' setting on the field display setting form.
   */
  public function testAbsoluteFileUrlFormatterConfiguration(): void {
    // Confirms the UX confirmation messaging after updating a file field
    // formatter display settings with 'absolute_url' setting enabled.
    $field_name = strtolower($this->randomMachineName());
    $type_name = 'article';
    $field_storage_settings = [
      'display_field' => '1',
      'display_default' => '1',
      'cardinality' => '1',
    ];
    $field_settings = [
      'description_field' => '1',
    ];
    $widget_settings = [];
    $this->createFileField($field_name, 'node', $type_name, $field_storage_settings, $field_settings, $widget_settings);

    $edit = [
      "fields[$field_name][type]" => 'file_url_plain',
    ];
    $this->drupalGet("admin/structure/types/manage/$type_name/display");
    $page = $this->getSession()->getPage();
    $page->fillField("fields[$field_name][type]", 'file_url_plain');
    $this->assertSession()->waitForElement('css', '.tabledrag-changed-warning');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains('Your settings have been saved.');

    $this->drupalGet("admin/structure/types/manage/$type_name/display");
    $page = $this->getSession()->getPage();
    $page->pressButton("{$field_name}_settings_edit");
    $this->assertSession()->waitForElement('css', '.ajax-new-content');
    $edit = [
      "fields[$field_name][settings_edit_form][settings][show_link_as]" => FileFormatterBase::ABSOLUTE_URL,
    ];
    foreach ($edit as $name => $value) {
      $page->fillField($name, $value);
    }
    $page->pressButton("{$field_name}_plugin_settings_update");
    $this->assertSession()->waitForElement('css', '.field-plugin-summary-cell > .ajax-new-content');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('Absolute URL');
  }

  /**
   * Retrieves the fid of the last inserted file.
   */
  protected function getLastFileId() {
    return (int) \Drupal::entityQueryAggregate('file')
      ->accessCheck(FALSE)
      ->aggregate('fid', 'max')
      ->execute()[0]['fid_max'];
  }

}
