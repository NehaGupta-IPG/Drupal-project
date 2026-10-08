<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests choosing the upload destination per file.
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
class FileFieldUriSchemeTest extends FileFieldTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests uploading to and moving between public and private storage.
   */
  public function testUriSchemeField(): void {
    $field_name = $this->randomMachineName();
    $type_name = 'article';
    $this->createFileField($field_name, 'node', $type_name, [
      'uri_scheme' => 'public',
      'uri_scheme_field' => TRUE,
    ]);

    // The location radios are not shown when the field setting is disabled.
    $other_field_name = $this->randomMachineName();
    $this->createFileField($other_field_name, 'node', $type_name);
    $this->drupalGet("node/add/$type_name");
    $this->assertSession()->fieldExists("{$field_name}[0][uri_scheme]");
    $this->assertSession()->fieldNotExists("{$other_field_name}[0][uri_scheme]");

    // Upload a file to private storage.
    $test_file = $this->getTestFile('text');
    $title = $this->randomMachineName();
    $this->drupalGet("node/add/$type_name");
    $this->submitForm([
      'title[0][value]' => $title,
      "files[{$field_name}_0]" => \Drupal::service('file_system')->realpath($test_file->getFileUri()),
      "{$field_name}[0][uri_scheme]" => 'private',
    ], 'Save');
    $node = $this->drupalGetNodeByTitle($title);
    $file = File::load($node->{$field_name}->target_id);
    $this->assertStringStartsWith('private://', $file->getFileUri());
    $this->assertFileExists($file->getFileUri());

    // The current location is selected when editing.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->fieldValueEquals("{$field_name}[0][uri_scheme]", 'private');

    // Move the file to public storage.
    $private_uri = $file->getFileUri();
    $this->submitForm(["{$field_name}[0][uri_scheme]" => 'public'], 'Save');
    $file = File::load($file->id());
    $this->assertStringStartsWith('public://', $file->getFileUri());
    $this->assertFileExists($file->getFileUri());
    $this->assertFileDoesNotExist($private_uri);
  }

  /**
   * Tests that a scheme that is not offered as an option is ignored.
   */
  public function testInvalidUriScheme(): void {
    $field_name = $this->randomMachineName();
    $type_name = 'article';
    $this->createFileField($field_name, 'node', $type_name, [
      'uri_scheme' => 'public',
      'uri_scheme_field' => TRUE,
    ]);

    $test_file = $this->getTestFile('text');
    $nid = $this->uploadNodeFile($test_file, $field_name, $type_name);
    $node = Node::load($nid);
    $file = File::load($node->{$field_name}->target_id);
    $this->assertStringStartsWith('public://', $file->getFileUri());

    // Submit a scheme that is not a visible, writable stream wrapper, which
    // bypasses the radios' allowed values.
    $this->drupalGet("node/$nid/edit");
    $client = $this->getSession()->getDriver()->getClient();
    $form = $client->getCrawler()->selectButton('Save')->form();
    $form->disableValidation();
    $form["{$field_name}[0][uri_scheme]"]->setValue('temporary');
    $client->submit($form);

    $file = File::load($file->id());
    $this->assertStringStartsWith('public://', $file->getFileUri());
  }

  /**
   * Tests that a failed move keeps the file in its current location.
   */
  public function testFailedMove(): void {
    $field_name = $this->randomMachineName();
    $type_name = 'article';
    $this->createFileField($field_name, 'node', $type_name, [
      'uri_scheme' => 'private',
      'uri_scheme_field' => TRUE,
    ]);

    $nid = $this->uploadNodeFile($this->getTestFile('text'), $field_name, $type_name);
    $file = File::load(Node::load($nid)->{$field_name}->target_id);
    $private_uri = $file->getFileUri();
    $this->assertStringStartsWith('private://', $private_uri);

    // A read-only source directory makes the source file impossible to
    // remove, so the move throws an exception.
    $file_system = \Drupal::service('file_system');
    $directory = $file_system->realpath($file_system->dirname($private_uri));
    chmod($directory, 0555);
    try {
      $this->drupalGet("node/$nid/edit");
      $this->submitForm(["{$field_name}[0][uri_scheme]" => 'public'], 'Save');
    }
    finally {
      chmod($directory, 0755);
    }

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->statusMessageContains('could not be moved to the selected location', 'error');
    $file = File::load($file->id());
    $this->assertSame($private_uri, $file->getFileUri());
    $this->assertFileExists($private_uri);
  }

  /**
   * Tests the location column in the multiple-value widget table.
   */
  public function testMultipleValueWidget(): void {
    $field_name = $this->randomMachineName();
    $type_name = 'article';
    $this->createFileField($field_name, 'node', $type_name, [
      'uri_scheme' => 'public',
      'uri_scheme_field' => TRUE,
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);

    $nid = $this->uploadNodeFile($this->getTestFile('text'), $field_name, $type_name);
    $this->drupalGet("node/$nid/edit");
    $this->assertSession()->elementExists('xpath', '//table//th[normalize-space(.)="Location"]');
    // The radios are rendered once, in the location column.
    $this->assertSession()->elementsCount('css', 'table input[name="' . $field_name . '[0][uri_scheme]"]', 2);
    $this->assertSession()->elementsCount('css', 'table td.radio input[name="' . $field_name . '[0][uri_scheme]"]', 2);
  }

}
