<?php

declare(strict_types=1);

namespace Drupal\Tests\inline_form_errors\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\inline_form_errors\FormErrorHandler;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests Drupal\inline_form_errors\FormErrorHandler.
 */
#[CoversClass(FormErrorHandler::class)]
#[Group('InlineFormErrors')]
class FormErrorHandlerTest extends UnitTestCase {

  /**
   * The form error handler.
   *
   * @var \Drupal\inline_form_errors\FormErrorHandler
   */
  protected $formErrorHandler;

  /**
   * The messenger.
   */
  protected MessengerInterface&MockObject $messenger;

  /**
   * The renderer.
   */
  protected RendererInterface&MockObject $renderer;

  /**
   * Form for testing.
   *
   * @var array
   */
  protected $testForm;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->renderer = $this->createMock(RendererInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);

    $this->formErrorHandler = new FormErrorHandler($this->getStringTranslationStub(), $this->renderer, $this->messenger);

    $this->testForm = [
      '#parents' => [],
      '#form_id' => 'test_form',
      '#array_parents' => [],
    ];
    $this->testForm['test1'] = [
      '#type' => 'textfield',
      '#title' => 'Test 1',
      '#parents' => ['test1'],
      '#array_parents' => ['test1'],
      '#id' => 'edit-test1',
    ];
    $this->testForm['test2'] = [
      '#type' => 'textfield',
      '#title' => 'Test 2 & a half',
      '#parents' => ['test2'],
      '#array_parents' => ['test2'],
      '#id' => 'edit-test2',
    ];
    $this->testForm['fieldset'] = [
      '#parents' => ['fieldset'],
      '#array_parents' => ['fieldset'],
      'test3' => [
        '#type' => 'textfield',
        '#title' => 'Test 3',
        '#parents' => ['fieldset', 'test3'],
        '#array_parents' => ['fieldset', 'test3'],
        '#id' => 'edit-test3',
      ],
    ];
    $this->testForm['test4'] = [
      '#type' => 'textfield',
      '#title' => 'Test 4',
      '#parents' => ['test4'],
      '#array_parents' => ['test4'],
      '#id' => 'edit-test4',
      '#error_no_message' => TRUE,
    ];
    $this->testForm['test5'] = [
      '#type' => 'textfield',
      '#parents' => ['test5'],
      '#array_parents' => ['test5'],
      '#id' => 'edit-test5',
    ];
    $this->testForm['test6'] = [
      '#type' => 'value',
      '#title' => 'Test 6',
      '#parents' => ['test6'],
      '#array_parents' => ['test6'],
      '#id' => 'edit-test6',
    ];
  }

  /**
   * Tests error messages inline.
   *
   * @legacy-covers ::handleFormErrors
   * @legacy-covers ::displayErrorMessages
   * @legacy-covers ::setElementErrorsFromFormState
   */
  public function testErrorMessagesInline(): void {
    $messages = [
      'no title given',
      'element is invisible',
      'this missing element is invalid',
      '3 errors have been found: <ul-comma-list-mock><li-mock>Test 1</li-mock><li-mock>Test 2 &amp; a half</li-mock><li-mock>Test 3</li-mock></ul-comma-list-mock>',
    ];

    $this->messenger->expects($this->exactly(count($messages)))
      ->method('addError')
      ->with(
        $this->callback(function (string $message) use (&$messages): bool {
          return array_shift($messages) === $message;
        }),
        FALSE
      );

    $this->renderer->expects($this->once())
      ->method('renderInIsolation')
      ->willReturnCallback(function ($render_array): string {
        $links = [];
        foreach ($render_array[1]['#items'] as $item) {
          $links[] = htmlspecialchars($item['#title']);
        }

        return $render_array[0]['#markup'] . '<ul-comma-list-mock><li-mock>' . implode('</li-mock><li-mock>', $links) . '</li-mock></ul-comma-list-mock>';
      });

    $form_state = new FormState();
    $form_state->setErrorByName('test1', 'invalid');
    $form_state->setErrorByName('test2', 'invalid');
    $form_state->setErrorByName('fieldset][test3', 'invalid');
    $form_state->setErrorByName('test4', 'no error message');
    $form_state->setErrorByName('test5', 'no title given');
    $form_state->setErrorByName('test6', 'element is invisible');
    $form_state->setErrorByName('missing_element', 'this missing element is invalid');
    $this->formErrorHandler->handleFormErrors($this->testForm, $form_state);

    // Assert the #errors is populated for proper input.
    $this->assertSame('invalid', $this->testForm['test1']['#errors']);
    $this->assertSame('invalid', $this->testForm['test2']['#errors']);
    $this->assertSame('invalid', $this->testForm['fieldset']['test3']['#errors']);
    $this->assertSame('no error message', $this->testForm['test4']['#errors']);
    $this->assertSame('no title given', $this->testForm['test5']['#errors']);
    $this->assertSame('element is invisible', $this->testForm['test6']['#errors']);
  }

  /**
   * Tests that an inherited field-level error is shown inline only once.
   *
   * A single error set on a parent element (for example via
   * $form_state->setErrorByName() on an entity reference field rendered with an
   * inline entity form) is inherited, through parent traversal, by every
   * renderable child. Without deduplication the same message is rendered inline
   * once per child. The error itself must remain on every element (so the
   * error indicator, e.g. aria-invalid, is preserved), but the message must be
   * suppressed on all but the first displaying element.
   *
   * @see https://www.drupal.org/project/drupal/issues/3450375
   * @legacy-covers ::handleFormErrors
   */
  public function testInheritedErrorMessageShownOnce(): void {
    $form = [
      '#parents' => [],
      '#form_id' => 'test_form',
      '#array_parents' => [],
    ];
    // A group whose children all inherit an error set on the group itself,
    // mirroring an inline entity form. The group and both children render an
    // inline message.
    $form['group'] = [
      '#type' => 'fieldset',
      '#title' => 'Group',
      '#parents' => ['group'],
      '#array_parents' => ['group'],
      '#id' => 'edit-group',
    ];
    $form['group']['one'] = [
      '#type' => 'textfield',
      '#title' => 'One',
      '#parents' => ['group', 'one'],
      '#array_parents' => ['group', 'one'],
      '#id' => 'edit-group-one',
      '#theme_wrappers' => ['form_element'],
    ];
    $form['group']['two'] = [
      '#type' => 'textfield',
      '#title' => 'Two',
      '#parents' => ['group', 'two'],
      '#array_parents' => ['group', 'two'],
      '#id' => 'edit-group-two',
      '#theme_wrappers' => ['form_element'],
    ];

    // This test only asserts the deduplication flags; message display is
    // covered by ::testErrorMessagesInline. The single retained error produces
    // one summary message.
    $this->renderer->expects($this->once())->method('renderInIsolation')->willReturn('summary');
    $this->messenger->expects($this->once())->method('addError');

    $form_state = new FormState();
    // Set the error on the parent element only.
    $form_state->setErrorByName('group', 'The group has an error.');
    $this->formErrorHandler->handleFormErrors($form, $form_state);

    // The error is inherited by the group and both children, so #errors - and
    // therefore the error indicator - is preserved on all three.
    $this->assertSame('The group has an error.', $form['group']['#errors']);
    $this->assertSame('The group has an error.', $form['group']['one']['#errors']);
    $this->assertSame('The group has an error.', $form['group']['two']['#errors']);

    // The message is kept on the first (outermost) displaying element and
    // suppressed on the children, so it is rendered inline exactly once.
    $this->assertArrayNotHasKey('#error_no_message', $form['group']);
    $this->assertTrue($form['group']['one']['#error_no_message']);
    $this->assertTrue($form['group']['two']['#error_no_message']);
  }

  /**
   * Tests that opting out of Inline Form Errors works.
   */
  public function testErrorMessagesNotInline(): void {
    // Asserts all messages are summarized.
    $messages = [
      'invalid',
      'invalid',
      'invalid',
      'no error message',
      'no title given',
      'element is invisible',
      'this missing element is invalid',
    ];

    $this->messenger->expects($this->exactly(count($messages)))
      ->method('addMessage')
      ->with(
        $this->callback(function (string $message) use (&$messages): bool {
          return array_shift($messages) === $message;
        }),
        'error',
        FALSE
      );

    $this->renderer->expects($this->never())
      ->method('renderInIsolation');

    $this->testForm['#disable_inline_form_errors'] = TRUE;

    $form_state = new FormState();
    $form_state->setErrorByName('test1', 'invalid');
    $form_state->setErrorByName('test2', 'invalid');
    $form_state->setErrorByName('fieldset][test3', 'invalid');
    $form_state->setErrorByName('test4', 'no error message');
    $form_state->setErrorByName('test5', 'no title given');
    $form_state->setErrorByName('test6', 'element is invisible');
    $form_state->setErrorByName('missing_element', 'this missing element is invalid');
    $this->formErrorHandler->handleFormErrors($this->testForm, $form_state);

    // Assert the #errors is populated for proper input.
    $this->assertSame('invalid', $this->testForm['test1']['#errors']);
    $this->assertSame('invalid', $this->testForm['test2']['#errors']);
    $this->assertSame('invalid', $this->testForm['fieldset']['test3']['#errors']);
    $this->assertSame('no error message', $this->testForm['test4']['#errors']);
    $this->assertSame('no title given', $this->testForm['test5']['#errors']);
    $this->assertSame('element is invisible', $this->testForm['test6']['#errors']);
  }

}
