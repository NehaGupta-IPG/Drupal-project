<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormObjectScopedFormState;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Form\FormObjectScopedFormState.
 */
#[CoversClass(FormObjectScopedFormState::class)]
#[Group('Form')]
class FormObjectScopedFormStateTest extends UnitTestCase {

  /**
   * Tests the form object is not delegated.
   */
  public function testGetFormObject(): void {
    $form_state = $this->prophesize(FormStateInterface::class);
    $form_state->getFormObject()->shouldNotBeCalled();
    $form_object = $this->prophesize(FormInterface::class)->reveal();

    $scoped_form_state = new FormObjectScopedFormState($form_state->reveal(), $form_object);
    $this->assertSame($form_object, $scoped_form_state->getFormObject());
    $this->assertNotInstanceOf(SubformStateInterface::class, $scoped_form_state);
  }

  /**
   * Tests callbacks.
   */
  public function testPrepareCallback(): void {
    $callback = 'test_callback';
    $form_state = $this->prophesize(FormStateInterface::class);
    $form_state->prepareCallback('::submitForm')->shouldNotBeCalled();
    $form_state->prepareCallback($callback)->willReturn($callback)->shouldBeCalledOnce();
    $form_object = $this->prophesize(FormInterface::class)->reveal();

    $scoped_form_state = new FormObjectScopedFormState($form_state->reveal(), $form_object);
    $this->assertSame([$form_object, 'submitForm'], $scoped_form_state->prepareCallback('::submitForm'));
    $this->assertSame($callback, $scoped_form_state->prepareCallback($callback));
  }

  /**
   * Tests nested scopes decorate the same form state.
   */
  public function testNesting(): void {
    $form_object = $this->prophesize(FormInterface::class)->reveal();
    $subform_object = $this->prophesize(FormInterface::class)->reveal();
    $nested_form_object = $this->prophesize(FormInterface::class)->reveal();
    $form_state = (new FormState())->setFormObject($form_object);

    $scoped_form_state = FormObjectScopedFormState::createForElement(['#form_object' => $subform_object], $form_state);
    $nested_form_state = FormObjectScopedFormState::createForElement(['#form_object' => $nested_form_object], $scoped_form_state);
    $this->assertSame($nested_form_object, $nested_form_state->getFormObject());
    $decorated_form_state = new \ReflectionProperty(FormObjectScopedFormState::class, 'decoratedFormState');
    $this->assertSame($form_state, $decorated_form_state->getValue($nested_form_state));

    $nested_form_state->setFormObject($subform_object);
    $this->assertSame($subform_object, $form_state->getFormObject());
    $this->assertSame($nested_form_object, $nested_form_state->getFormObject());
  }

  /**
   * Tests elements without another form object.
   */
  public function testCreateForElementUnchanged(): void {
    $form_object = $this->prophesize(FormInterface::class)->reveal();
    $form_state = (new FormState())->setFormObject($form_object);

    $this->assertSame($form_state, FormObjectScopedFormState::createForElement([], $form_state));
    $this->assertSame($form_state, FormObjectScopedFormState::createForElement(['#form_object' => NULL], $form_state));
    $this->assertSame($form_state, FormObjectScopedFormState::createForElement(['#form_object' => $form_object], $form_state));

    $scoped_form_state = FormObjectScopedFormState::createForElement(['#form_object' => $this->prophesize(FormInterface::class)->reveal()], $form_state);
    $this->assertNotSame($form_state, $scoped_form_state);
    $this->assertSame($scoped_form_state, FormObjectScopedFormState::createForElement(['#form_object' => $scoped_form_state->getFormObject()], $scoped_form_state));
  }

  /**
   * Tests an invalid form object.
   */
  public function testCreateForElementInvalid(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The #form_object property must implement Drupal\Core\Form\FormInterface, stdClass given.');
    FormObjectScopedFormState::createForElement(['#form_object' => new \stdClass()], new FormState());
  }

  /**
   * Tests values are not relative to the scoped element.
   */
  public function testValues(): void {
    $form_state = new FormState();
    $form_state->setValues(['foo' => 'bar', 'subform' => ['baz' => 'qux']]);
    $scoped_form_state = new FormObjectScopedFormState($form_state, $this->prophesize(FormInterface::class)->reveal());

    $this->assertSame('bar', $scoped_form_state->getValue('foo'));
    $this->assertSame('qux', $scoped_form_state->getValue(['subform', 'baz']));

    $scoped_form_state->setValue(['subform', 'baz'], 'quux');
    $this->assertSame('quux', $form_state->getValue(['subform', 'baz']));
  }

  /**
   * Tests errors are not relative to the scoped element.
   */
  public function testSetErrorByName(): void {
    $form_state = $this->prophesize(FormStateInterface::class);
    $form_state->setErrorByName('subform][baz', 'Error')->shouldBeCalledOnce();

    $scoped_form_state = new FormObjectScopedFormState($form_state->reveal(), $this->prophesize(FormInterface::class)->reveal());
    $this->assertSame($scoped_form_state, $scoped_form_state->setErrorByName('subform][baz', 'Error'));
  }

}
