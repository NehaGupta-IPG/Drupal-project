<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Submit;
use Drupal\Core\Render\Element\Textfield;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the caching of the form array when the initial build is cached.
 *
 * When ElementInterface objects are used to build the form array, such as with
 * field widgets, if the initial form build is cached, such as if the request
 * for the form is an AJAX POST request to display the form in a modal or
 * off-canvas, the form build array that is retrieved from cache when the form
 * is submitted needs to allow the form state values to populate correctly from
 * submitted input.
 */
#[Group('Form')]
class FormArrayCacheTest extends KernelTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'form_test_state_persist';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if (\Drupal::requestStack()->getCurrentRequest()->getMethod() === 'POST') {
      $form_state->setCached();
    }

    /** @var \Drupal\Core\Render\ElementInfoManagerInterface $element_manager */
    $element_manager = \Drupal::service('plugin.manager.element_info');
    $form_element = $element_manager->fromRenderable($form);
    $text_field = $form_element->createChild('title', Textfield::class);
    $text_field->title = 'title';
    $text_field->default_value = NULL;
    $text_field->required = TRUE;

    $submit = $form_element->createChild('submit', Submit::class);
    $submit->title = 'submit';
    $submit->value = 'Submit';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
  }

  /**
   * Test that a cacheable initial form build caches correctly.
   */
  public function testFormArrayCacheWithInitialPostRequest(): void {
    // Simulate an initial request to a form via POST, such as via an AJAX
    // request to be opened in a modal or off-canvas.
    $request = Request::create('/', 'POST');
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::requestStack()->push($request);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this, $form_state);
    $this->assertArrayHasKey('title', $form);
    $this->assertEquals('textfield', $form['title']['#type']);
    $this->assertArrayHasKey('submit', $form);
    $this->assertEquals('submit', $form['submit']['#type']);
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $renderer->renderRoot($form);

    // Simulate a form submission that submits a new value for the title.
    $new_title = 'new title';
    $request->request->set('form_id', $this->getFormId());
    $request->request->set('form_build_id', $form['#build_id']);
    $request->request->set('title', $new_title);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this, $form_state);
    // The form array retrieved from cache should not prevent the submitted
    // input from being mapped to the form elements '#value' properties/form
    // state values.
    $this->assertEquals($new_title, $form_state->getValue('title'));
  }

}
