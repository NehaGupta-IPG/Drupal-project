<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Form;

use Drupal\Component\DependencyInjection\ReverseContainer;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Callback\ElementProcessCallbackInterface;
use Drupal\Core\Render\Callback\ElementTypeProcessCallback;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\Core\Form\FormBuilder
 * @group Form
 */
class FormBuilderCallbackTest extends FormTestBase {

  /**
   * Defaults for element info.
   *
   * @var array
   */
  protected array $elementInfoDefaults = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set(ReverseContainer::class, new ReverseContainer($container));
    $container->set(ElementInfoManagerInterface::class, $this->elementInfo);
    \Drupal::setContainer($container);
  }

  public function testElementType(): void {
    $this->elementInfoDefaults['test_type'] = [
      '#attributes' => ['class' => ['test-type-class']],
    ];
    $form_object = new GenericTestForm([
      'element_with_type' => [
        '#type' => 'test_type',
      ],
    ]);
    $form_state = new FormState();
    $form = $this->formBuilder->buildForm($form_object, $form_state);
    $this->assertSame(
      ['test-type-class'],
      $form['element_with_type']['#attributes']['class'],
    );
  }

  public function testElementProcessSimple(): void {
    $form_object = new GenericTestForm([
      'element' => [
        '#process' => [
          new AddClassProcess('aaa'),
        ],
      ],
    ]);
    $form_state = new FormState();
    $form = $this->formBuilder->buildForm($form_object, $form_state);
    $this->assertSame(
      ['aaa'],
      $form['element']['#attributes']['class'],
    );
  }

  /**
   * Tests that FormBuilder supports by-reference parameters in '#process'.
   *
   * Element '#process' callbacks are allowed to expect by-reference  parameters
   * for legacy reasons. However, they are not supposed to actually replace
   * these values.
   */
  public function testElementProcessReferenceParameters(): void {
    $form_object = new GenericTestForm([
      'element' => [
        '#type' => 'test_type',
        '#process' => [
          // Element '#process' callbacks are allowed to expect by-reference
          // parameters for legacy reasons. However, they are not supposed to
          // actually replace these values.
          function (array &$element, FormStateInterface &$form_state, array &$complete_form): array {
            $complete_form['#x'] = 'y';
            return $element;
          },
        ],
      ],
    ]);
    $form_state = new FormState();
    $form = $this->formBuilder->buildForm($form_object, $form_state);
    $this->assertSame('y', $form['#x']);
  }

  /**
   * @covers \Drupal\Core\Render\Callback\ElementTypeProcessCallback
   */
  public function testProcessWithType(): void {
    $this->elementInfoDefaults['test_type'] = [
      '#process' => [
        new AddClassProcess('test-type-class'),
      ],
    ];
    $form_object = new GenericTestForm([
      'element' => [
        '#type' => 'test_type',
        '#process' => [
          new AddClassProcess('aaa'),
          new ElementTypeProcessCallback($this->elementInfo),
          new AddClassProcess('bbb'),
        ],
      ],
    ]);
    $form_state = new FormState();
    $form = $this->formBuilder->buildForm($form_object, $form_state);
    $this->assertSame(
      ['aaa', 'test-type-class', 'bbb'],
      $form['element']['#attributes']['class'],
    );
  }

  /**
   * @covers \Drupal\Core\Render\Callback\ElementTypeProcessCallback
   */
  public function testElementTypeProcessReferenceParameters(): void {
    $this->elementInfoDefaults['test_type'] = [
      '#process' => [
        // Verify that '#process' callbacks with by-reference parameters are
        // fully supported.
        // None of these callbacks actually replace the form state, but
        function (array &$element, FormStateInterface &$form_state, array &$complete_form): array {
          $complete_form['#x'] = 'y';
          return $element;
        },
      ],
    ];
    $form_object = new GenericTestForm([
      'element' => [
        '#type' => 'test_type',
        '#process' => [
          new ElementTypeProcessCallback($this->elementInfo),
        ],
      ],
    ]);
    $form_state = new FormState();
    $form = $this->formBuilder->buildForm($form_object, $form_state);
    $this->assertSame('y', $form['#x']);
  }

  /**
   * @covers \Drupal\Core\Render\Callback\ElementTypeProcessCallback
   */
  public function testSerializeElementTypeProcessCallback(): void {
    $callback = new ElementTypeProcessCallback($this->elementInfo);
    $restored_callback = unserialize(serialize($callback));
    $reflection_property = new \ReflectionProperty(ElementTypeProcessCallback::class, 'elementInfoManager');
    $this->assertSame(
      $reflection_property->getValue($callback),
      $reflection_property->getValue($restored_callback),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo($type): array {
    return $this->elementInfoDefaults[$type] ?? parent::getInfo($type);
  }

}

class AddClassProcess implements ElementProcessCallbackInterface {

  public function __construct(
    protected readonly string $class,
  ) {}

  public function __invoke(array $element, FormStateInterface $form_state, array &$complete_form): array {
    $element['#attributes']['class'][] = $this->class;
    return $element;
  }

}

class GenericTestForm implements FormInterface {

  public function __construct(
    protected readonly array $form,
  ) {}

  public function getFormId(): string {
    return 'test_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    return $this->form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
