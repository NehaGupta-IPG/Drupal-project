<?php

namespace Drupal\Core\Form;

use Drupal\Core\EventSubscriber\RedirectResponseSubscriber;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\CallableResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides submission processing for forms.
 */
class FormSubmitter implements FormSubmitterInterface {

  public function __construct(
    protected RequestStack $requestStack,
    protected UrlGeneratorInterface $urlGenerator,
    protected RedirectResponseSubscriber $redirectResponseSubscriber,
    protected CallableResolver $callableResolver,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function doSubmitForm(&$form, FormStateInterface &$form_state) {
    if (!$form_state->isSubmitted()) {
      return;
    }

    // Execute form submit handlers.
    $triggering_element = $form_state->getTriggeringElement();
    $limit_element_submit = isset($triggering_element['#limit_element_submit']) && $triggering_element['#limit_element_submit'] !== FALSE ? $triggering_element['#limit_element_submit'] : [];
    $this->submitFormElement($form, $form_state, $limit_element_submit);
    $this->executeSubmitHandlers($form, $form_state);

    // If batches were set in the submit handlers, we process them now,
    // possibly ending execution. We make sure we do not react to the batch
    // that is already being processed (if a batch operation performs a
    // \Drupal\Core\Form\FormBuilderInterface::submitForm).
    if ($batch = &$this->batchGet() && !isset($batch['current_set'])) {
      // Store $form_state information in the batch definition.
      $batch['form_state'] = $form_state;

      $batch['progressive'] = !$form_state->isProgrammed();
      $response = batch_process();
      // If the batch has been completed and _batch_finished() called then
      // $batch will be NULL.
      if ($batch && $batch['progressive']) {
        return $response;
      }

      // Execution continues only for programmatic forms.
      // For 'regular' forms, we get redirected to the batch processing
      // page. Form redirection will be handled in _batch_finished(),
      // after the batch is processed.
    }

    // Set a flag to indicate the form has been processed and executed.
    $form_state->setExecuted();

    // If no response has been set, process the form redirect.
    if (!$form_state->getResponse() && $redirect = $this->redirectForm($form_state)) {
      $form_state->setResponse($redirect);
    }

    // If there is a response was set, return it instead of continuing.
    if (($response = $form_state->getResponse()) && $response instanceof Response) {
      return $response;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeSubmitHandlers(&$form, FormStateInterface &$form_state) {
    // If there was a button pressed, use its handlers.
    $handlers = $form_state->getSubmitHandlers();
    // Otherwise, check for a form-level handler.
    if (!$handlers && !empty($form['#submit'])) {
      $handlers = $form['#submit'];
    }

    foreach ($handlers as $callback) {
      // Check if a previous _submit handler has set a batch, but make sure we
      // do not react to a batch that is already being processed (for instance
      // if a batch operation performs a
      // \Drupal\Core\Form\FormBuilderInterface::submitForm()).
      if (($batch = &$this->batchGet()) && !isset($batch['id'])) {
        // Some previous submit handler has set a batch. To ensure correct
        // execution order, store the call in a special 'control' batch set.
        // See _batch_next_set().
        $batch['sets'][] = ['form_submit' => $callback];
        $batch['has_form_submits'] = TRUE;
      }
      else {
        $callable = $this->callableResolver->getCallableFromDefinition($form_state->prepareCallback($callback));
        $callable($form, $form_state);
      }
    }
  }

  /**
   * Performs submission on form elements.
   *
   * @param array $elements
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $limit_element_submit
   *   An array of form element #array_parents arrays to limit element level
   *   submitting to.
   */
  protected function submitFormElement(array &$elements, FormStateInterface $form_state, array $limit_element_submit = []): void {
    // Recurse through all children.
    foreach (Element::children($elements) as $key) {
      if (isset($elements[$key]) && is_array($elements[$key])) {
        $this->submitFormElement($elements[$key], $form_state, $limit_element_submit);
      }
    }

    // Call any element-specific submit handlers. These must act on the element
    // #value data.
    if (isset($elements['#element_submit']) && (empty($limit_element_submit) || $this->shouldElementBeSubmitted($elements, $limit_element_submit))) {
      foreach ($elements['#element_submit'] as $callback) {
        $complete_form = &$form_state->getCompleteForm();
        $callable = $this->callableResolver->getCallableFromDefinition($form_state->prepareCallback($callback));
        $callable($elements, $form_state, $complete_form);
      }
    }
  }

  /**
   * Determines whether an element's submit callbacks should run.
   *
   * @param array $element
   *   The element itself.
   * @param array $limit_element_submit
   *   The array of parents which should be element submitted.
   *
   * @return bool
   *   Whether the element belongs to one of the submitted subtrees.
   */
  protected function shouldElementBeSubmitted(array $element, array $limit_element_submit): bool {
    $array_parents = $element['#array_parents'];
    foreach ($limit_element_submit as $parents) {
      // Compare complete keys so group_1 does not also submit group_10.
      if (array_slice($array_parents, 0, count($parents)) === $parents) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function redirectForm(FormStateInterface $form_state) {
    $redirect = $form_state->getRedirect();

    $this->redirectResponseSubscriber->setIgnoreDestination($form_state->getIgnoreDestination());

    // Allow using redirect responses directly if needed.
    if ($redirect instanceof RedirectResponse) {
      return $redirect;
    }

    $url = NULL;
    // Check for a route-based redirection.
    if ($redirect instanceof Url) {
      $url = $redirect->setAbsolute()->toString();
    }
    // If no redirect was specified, redirect to the current path.
    elseif ($redirect === NULL) {
      $request = $this->requestStack->getCurrentRequest();
      $url = $this->urlGenerator->generateFromRoute('<current>', [], [
        'query' => $request->query->all(),
        'absolute' => TRUE,
      ]);
    }

    if ($url) {
      // According to RFC 7231, 303 See Other status code must be used to
      // redirect user agent (and not default 302 Found).
      // @see http://tools.ietf.org/html/rfc7231#section-6.4.4
      return new RedirectResponse($url, Response::HTTP_SEE_OTHER);
    }
  }

  /**
   * Wraps batch_get().
   */
  protected function &batchGet() {
    return batch_get();
  }

}
