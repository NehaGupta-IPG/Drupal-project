<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Form;

use Drupal\Core\Form\FormAjaxException;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\form_test\Form\FormTestFormObjectScopeForm;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests Form API callbacks within elements with #form_object.
 *
 * @see \Drupal\form_test\Form\FormTestFormObjectScopeForm
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class FormObjectScopeTest extends KernelTestBase {

  /**
   * The form and button handlers of the test form.
   */
  protected const HANDLERS = [
    'validateButton',
    'submitButton',
    'submitStale',
    'ajaxButton',
    'validateForm',
    'submitForm',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'form_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    FormTestFormObjectScopeForm::$log = [];
    FormTestFormObjectScopeForm::$values = [];
  }

  /**
   * Tests the callbacks run while building a form.
   */
  public function testBuild(): void {
    $this->container->get('form_builder')->getForm(FormTestFormObjectScopeForm::class);
    $this->assertSame([
      'processRoot parent/parent',
      'processRoot child1/child1',
      'valueCallback -/child1',
      'processRoot grandchild/grandchild',
      'valueCallback -/grandchild',
      'afterBuildRoot grandchild/grandchild',
      'processElement child1/child1',
      'afterBuildRoot child1/child1',
      'processElement parent/parent',
      'processEmbed parent/parent',
      'processRoot embedded/embedded',
      'valueCallback -/embedded',
      'afterBuildRoot embedded/embedded',
      'afterBuildRoot parent/parent',
    ], FormTestFormObjectScopeForm::$log);
  }

  /**
   * Tests a form embedded without #form_object.
   */
  public function testBuildUnscoped(): void {
    $this->container->get('form_builder')->getForm(FormTestFormObjectScopeForm::class, FALSE);
    $this->assertSame([
      'processRoot parent/parent',
      'processRoot parent/parent',
      'valueCallback -/parent',
      'processRoot grandchild/grandchild',
      'valueCallback -/grandchild',
      'afterBuildRoot grandchild/grandchild',
      'processElement parent/parent',
      'afterBuildRoot parent/parent',
      'processElement parent/parent',
      'processEmbed parent/parent',
      'processRoot embedded/embedded',
      'valueCallback -/embedded',
      'afterBuildRoot embedded/embedded',
      'afterBuildRoot parent/parent',
    ], FormTestFormObjectScopeForm::$log);
  }

  /**
   * Tests the handlers run when a button is pressed.
   */
  #[DataProvider('providerSubmit')]
  public function testSubmit(string $button, string $value, bool $scoped, array $expected): void {
    $form_state = (new FormState())->setValues([
      'parent_text' => 'Parent text',
      'child' => ['text' => 'Child text'],
      $button => $value,
    ]);
    $this->container->get('form_builder')->submitForm(FormTestFormObjectScopeForm::class, $form_state, $scoped);

    $this->assertSame([], $form_state->getErrors());
    $this->assertSame($expected, $this->getLog());
    $submit_callback = explode(' ', $expected[1])[0];
    $this->assertSame('Child text', FormTestFormObjectScopeForm::$values[$submit_callback]);
  }

  /**
   * Provides test data for testSubmit().
   */
  public static function providerSubmit(): array {
    return [
      'button' => [
        'child_button',
        'Button',
        TRUE,
        ['validateButton child1/child1', 'submitButton child1/child1'],
      ],
      'nested button' => [
        'grandchild_button',
        'Button',
        TRUE,
        ['validateButton grandchild/grandchild', 'submitButton grandchild/grandchild'],
      ],
      'button embedded in #process' => [
        'embedded_button',
        'Button',
        TRUE,
        ['validateButton embedded/embedded', 'submitButton embedded/embedded'],
      ],
      'button without handlers' => [
        'fallback',
        'Fallback',
        TRUE,
        ['validateForm parent/parent', 'submitForm parent/parent'],
      ],
      'button with parent handlers' => [
        'parent_button',
        'Parent button',
        TRUE,
        ['validateButton parent/child1', 'submitButton parent/child1'],
      ],
      'form button' => [
        'op',
        'Submit',
        TRUE,
        ['validateForm parent/parent', 'submitForm parent/parent'],
      ],
      'unscoped button' => [
        'child_button',
        'Button',
        FALSE,
        ['validateButton parent/parent', 'submitButton parent/parent'],
      ],
    ];
  }

  /**
   * Tests element validation and #limit_validation_errors.
   */
  public function testValidate(): void {
    // The button limits validation errors to the child form.
    $form_state = (new FormState())->setValues([
      'child' => ['text' => 'Child text'],
      'child_button' => 'Button',
    ]);
    $this->container->get('form_builder')->submitForm(FormTestFormObjectScopeForm::class, $form_state);

    $this->assertSame([], $form_state->getErrors());
    $this->assertFalse($form_state->hasValue('parent_text'));
    $this->assertSame([
      'elementValidate parent/parent',
      'elementValidate child1/child1',
      'elementValidate grandchild/grandchild',
      'elementValidate embedded/embedded',
      'validateButton child1/child1',
      'submitButton child1/child1',
    ], $this->getLog(['elementValidate', ...self::HANDLERS]));
    $this->assertSame('Child text', FormTestFormObjectScopeForm::$values['elementValidate']);
  }

  /**
   * Tests Ajax submissions, including ones of a cached form.
   */
  public function testAjax(): void {
    $form = $this->container->get('form_builder')->getForm(FormTestFormObjectScopeForm::class);

    // The Ajax callback runs on the rebuilt form.
    $form = $this->ajaxSubmit($form['#build_id'], 'child_button');
    $this->assertSame([
      'validateButton child1/child1',
      'submitButton child1/child1',
      'ajaxButton child2/child2',
    ], $this->getLog());
    $this->assertSame('Child text', FormTestFormObjectScopeForm::$values['ajaxButton']);

    // The cached form holds the child form object of the second build.
    $form = $this->ajaxSubmit($form['#build_id'], 'child_button');
    $this->assertSame([
      'validateButton child2/child2',
      'submitButton child2/child2',
      'ajaxButton child3/child3',
    ], $this->getLog());

    // The rebuilt form no longer has the triggering element.
    $form = $this->ajaxSubmit($form['#build_id'], 'stale');
    $this->assertArrayNotHasKey('stale', $form['child']);
    $this->assertSame([
      'validateForm parent/parent',
      'submitStale child3/child3',
      'ajaxButton child3/child3',
    ], $this->getLog());
  }

  /**
   * Submits the form via Ajax and builds the Ajax response.
   *
   * @param string $build_id
   *   The form build ID.
   * @param string $button
   *   The name of the triggering element.
   *
   * @return array
   *   The processed form.
   */
  protected function ajaxSubmit(string $build_id, string $button): array {
    FormTestFormObjectScopeForm::$log = [];
    $request = Request::create('/?' . FormBuilderInterface::AJAX_FORM_REQUEST . '=1', 'POST', [
      'form_id' => 'form_test_form_object_scope',
      'form_build_id' => $build_id,
      'parent_text' => 'Parent text',
      'child' => ['text' => 'Child text'],
      '_triggering_element_name' => $button,
    ]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request_stack = $this->container->get('request_stack');
    $request_stack->push($request);
    $form_state = new FormState();
    try {
      $this->container->get('form_builder')->buildForm(FormTestFormObjectScopeForm::class, $form_state);
      $this->fail('The form was not submitted via Ajax.');
    }
    catch (FormAjaxException $e) {
      $form = $e->getForm();
      $this->container->get('form_ajax_response_builder')->buildResponse($request, $form, $e->getFormState(), []);
    }
    finally {
      $request_stack->pop();
    }
    return $form;
  }

  /**
   * Gets the log entries of the given callbacks.
   *
   * @param string[] $callbacks
   *   The callbacks.
   *
   * @return string[]
   *   The log entries.
   */
  protected function getLog(array $callbacks = self::HANDLERS): array {
    return array_values(array_filter(FormTestFormObjectScopeForm::$log, fn (string $entry): bool => in_array(explode(' ', $entry)[0], $callbacks, TRUE)));
  }

}
