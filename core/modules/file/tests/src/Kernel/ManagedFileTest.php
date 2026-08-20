<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Element\ManagedFile;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Managed file element test.
 *
 * @see \Drupal\file\Element\ManagedFile
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
class ManagedFileTest extends FileManagedUnitTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'form_test_managed_file';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['managed_file'] = [
      '#type' => 'managed_file',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Tests that managed file elements can be programmatically submitted.
   */
  public function testManagedFileElement(): void {
    $form_state = new FormState();
    $values['managed_file'] = NULL;
    $form_state->setValues($values);
    $this->container->get('form_builder')->submitForm($this, $form_state);
    // Should submit without any errors.
    $this->assertEquals(0, count($form_state->getErrors()));
  }

  /**
   * Tests that the upload AJAX callback delivers messages as AJAX commands.
   */
  public function testUploadAjaxCallbackMessages(): void {
    $messenger = $this->container->get('messenger');
    $messenger->addError('The file is too big.');

    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($this, $form_state);
    $request = new Request(['element_parents' => 'managed_file']);
    $commands = ManagedFile::uploadAjaxCallback($form, $form_state, $request)->getCommands();

    // The replaced markup only carries the container the messages go into, so
    // that the next upload replaces stale messages away with the element.
    $this->assertSame('insert', $commands[0]['command']);
    $this->assertStringContainsString('file-upload-messages', (string) $commands[0]['data']);
    $this->assertStringNotContainsString('The file is too big.', (string) $commands[0]['data']);

    // The message itself is delivered as a command targeting that container.
    $this->assertSame('message', $commands[1]['command']);
    $this->assertSame('The file is too big.', (string) $commands[1]['message']);
    $this->assertSame('error', $commands[1]['messageOptions']['type']);
    $this->assertStringStartsWith('#file-upload-messages-managed-file', $commands[1]['messageWrapperQuerySelector']);
    $this->assertFalse($commands[1]['clearPrevious']);
    $this->assertCount(2, $commands);

    // The messages were consumed by the response.
    $this->assertSame([], $messenger->all());
  }

}
