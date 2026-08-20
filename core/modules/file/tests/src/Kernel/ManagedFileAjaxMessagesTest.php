<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\file\Element\ManagedFile;
use Drupal\file_module_test\Form\FileModuleTestForm;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that the managed_file upload callback delivers messages as commands.
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
class ManagedFileAjaxMessagesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'file_module_test'];

  /**
   * Builds the test form and calls the upload AJAX callback.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response of ManagedFile::uploadAjaxCallback().
   */
  protected function runUploadAjaxCallback() {
    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm(FileModuleTestForm::class, $form_state);
    $request = new Request(['element_parents' => 'nested/file']);
    return ManagedFile::uploadAjaxCallback($form, $form_state, $request);
  }

  /**
   * Messages are delivered as message commands, not in the element markup.
   */
  public function testMessagesAreDeliveredAsCommands(): void {
    $messenger = $this->container->get('messenger');
    $messenger->addError('The file is too big.');
    $messenger->addStatus('A previous file was replaced.');

    $commands = $this->runUploadAjaxCallback()->getCommands();

    // The element is replaced first, then the messages are placed into the
    // container the replacement delivered.
    $insert_commands = array_filter($commands, fn (array $command) => $command['command'] === 'insert');
    $this->assertCount(1, $insert_commands);
    $insert_index = array_key_first($insert_commands);
    $insert_command = $insert_commands[$insert_index];
    $this->assertStringContainsString('file-upload-messages', (string) $insert_command['data']);
    $this->assertStringNotContainsString('The file is too big.', (string) $insert_command['data']);

    $message_commands = array_filter($commands, fn (array $command) => $command['command'] === 'message');
    $this->assertCount(2, $message_commands);
    $this->assertGreaterThan($insert_index, array_key_first($message_commands));
    $message_commands = array_values($message_commands);
    $this->assertSame('The file is too big.', (string) $message_commands[0]['message']);
    $this->assertSame('error', $message_commands[0]['messageOptions']['type']);
    $this->assertSame('A previous file was replaced.', (string) $message_commands[1]['message']);
    $this->assertSame('status', $message_commands[1]['messageOptions']['type']);
    foreach ($message_commands as $message_command) {
      $this->assertMatchesRegularExpression('/^#[\w-]+ \.file-upload-messages$/', $message_command['messageWrapperQuerySelector']);
      $this->assertFalse($message_command['clearPrevious']);
    }

    // The messages were consumed.
    $this->assertSame([], $messenger->all());
  }

  /**
   * Without messages, the response only replaces the element.
   */
  public function testNoMessagesNoMessageCommands(): void {
    $commands = $this->runUploadAjaxCallback()->getCommands();

    $this->assertSame([], array_filter($commands, fn (array $command) => $command['command'] === 'message'));
    $insert_commands = array_values(array_filter($commands, fn (array $command) => $command['command'] === 'insert'));
    $this->assertCount(1, $insert_commands);
    $this->assertStringContainsString('file-upload-messages', (string) $insert_commands[0]['data']);
  }

}
