<?php

namespace Drupal\Core\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\OpenModalDialogWithUrl;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\UpdateBuildIdCommand;
use Drupal\Core\Render\MainContent\MainContentRendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Utility\CallableResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Builds an AJAX form response.
 *
 * Given the current request, a form render array, its form state, and any AJAX
 * commands to apply to the form, build a response object.
 */
class FormAjaxResponseBuilder implements FormAjaxResponseBuilderInterface {

  use AjaxFormHelperTrait;

  public function __construct(
    protected MainContentRendererInterface $ajaxRenderer,
    protected RouteMatchInterface $routeMatch,
    protected CallableResolver $callableResolver,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function buildResponse(Request $request, array $form, FormStateInterface $form_state, array $commands) {
    // If the form build ID has changed, issue an Ajax command to update it.
    if (isset($form['#build_id_old']) && $form['#build_id_old'] !== $form['#build_id']) {
      $commands[] = new UpdateBuildIdCommand($form['#build_id_old'], $form['#build_id']);
    }

    // We need to return the part of the form (or some other content) that needs
    // to be re-rendered so the browser can update the page with changed
    // content. It is up to the #ajax['callback'] function of the element (may
    // or may not be a button) that triggered the Ajax request to determine what
    // needs to be rendered.
    $callback = NULL;
    if (($triggering_element = $form_state->getTriggeringElement()) && isset($triggering_element['#ajax']['callback'])) {
      $callback = $triggering_element['#ajax']['callback'];
    }
    $callback = $form_state->prepareCallback($callback);
    if (empty($callback)) {
      throw new HttpException(500, 'The specified #ajax callback is empty or not callable.');
    }
    try {
      $callback = $this->callableResolver->getCallableFromDefinition($callback);
    }
    catch (\InvalidArgumentException $e) {
      throw new HttpException(500, 'The specified #ajax callback is empty or not callable.', $e);
    }
    $result = call_user_func_array($callback, [&$form, &$form_state, $request]);

    // If the callback is an #ajax callback, the result is a render array, and
    // we need to turn it into an AJAX response, so that we can add any commands
    // we got earlier; typically the UpdateBuildIdCommand when handling an AJAX
    // submit from a cached page.
    if ($result instanceof AjaxResponse) {
      $response = $result;
    }
    else {
      // At this point we know callback returned a render element. If the
      // element is part of the group (#group is set on it) it won't be rendered
      // unless we remove #group from it. This is caused by
      // \Drupal\Core\Render\Element\RenderElementBase::preRenderGroup(), which
      // prevents all members of groups from being rendered directly.
      if (!empty($result['#group'])) {
        unset($result['#group']);
      }

      /** @var \Drupal\Core\Ajax\AjaxResponse $response */
      $response = $this->ajaxRenderer->renderResponse($result, $request, $this->routeMatch);
    }

    foreach ($commands as $command) {
      $response->addCommand($command, TRUE);
    }
    return $response;
  }

  /**
   * Opens a routed form in a dialog, preserving the calling form.
   *
   * Set this as a button's #ajax callback and use #url for the route of the
   * form to open. The optional #ajax['dialog'] settings configure the dialog.
   *
   * The opened form uses its own validation and submit handlers. Its submit
   * buttons receive default #ajax settings unless they already have their own.
   * Validation errors and multistep rebuilds are displayed within the dialog.
   *
   * After successful submission, the opening button's #dialog_submit callbacks
   * can use the result to update the calling form state before rebuilding it.
   * Each callback receives the dialog form state, the opening button, and the
   * calling form state. Closing the dialog without submitting leaves the
   * calling form unchanged.
   *
   * Both forms and their states are retained through the Form API cache. The
   * request supplies only the calling form's build ID; callbacks and defaults
   * are retrieved from the cached state.
   *
   * @param array $form
   *   The calling form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The calling form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response opening the dialog.
   */
  public function openDialog(array &$form, FormStateInterface $form_state, Request $request): AjaxResponse {
    $element = $form_state->getTriggeringElement();
    $url = $element['#url'];
    $options = $element['#ajax']['dialog'] ?? [];
    // @todo Use scoped modals once
    //   https://www.drupal.org/project/drupal/issues/2741877 is resolved.
    $options += ['target' => 'drupal-dialog-' . $element['#id'], 'modal' => TRUE];

    $form_builder = \Drupal::formBuilder();
    if (!$parent_form = $form_builder->getCache($form['#build_id'], new FormState())) {
      throw new HttpException(400, 'The calling form is no longer available.');
    }

    if (isset($form['#attached']['placeholders'][$form['#action']])) {
      $parent_form['#action'] = $form_builder->renderPlaceholderFormAction()['#markup'];
    }

    $form_state->set('ajax_dialog', [
      'input' => $form_state->getUserInput(),
      'url' => $request->getUri(),
      'element' => $element,
      'selector' => '#' . ltrim($options['target'], '#'),
    ]);
    $form_state->set('form_defaults', [
      '#ajax' => ['callback' => 'form_ajax_response_builder:ajaxSubmit'],
    ]);
    $form_builder->setCache($form['#build_id'], $parent_form, $form_state);

    return (new AjaxResponse())->addCommand(new OpenModalDialogWithUrl($url->toString(), $options, [
      'httpMethod' => 'POST',
      'dialogType' => 'dialog',
      'submit' => ['form_parent' => $form['#build_id']],
    ]));
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state): AjaxResponse {
    $parent_state = (new FormState())->setRequestMethod('POST');
    $form_builder = \Drupal::formBuilder();
    if (!$parent_form = $form_builder->getCache($form_state->get('form_parent'), $parent_state)) {
      throw new HttpException(400, 'The calling form is no longer available.');
    }

    $dialog = $parent_state->get('ajax_dialog');
    $parent_state->setUserInput($dialog['input']);

    // Rebuild in the calling form's routing context so that its AJAX URLs
    // still point to that form's route, not the route of the dialog form.
    // @todo Revisit the routing context needed for AJAX URLs once
    //   https://www.drupal.org/project/drupal/issues/2504115 respects #action.
    // @todo Revisit caller reconstruction with dedicated form actions once
    //   https://www.drupal.org/project/drupal/issues/2503429 is resolved.
    $parent_state->setRebuild();
    $parent_state->addRebuildInfo('copy', ['#action' => TRUE, '#build_id' => TRUE]);
    $request = Request::create($dialog['url'], server: \Drupal::request()->server->all());
    $request->setSession(\Drupal::request()->getSession());
    $request->attributes->add(\Drupal::service('router')->matchRequest($request));
    $request_stack = \Drupal::service('request_stack');
    $request_stack->push($request);

    try {
      foreach ($dialog['element']['#dialog_submit'] ?? [] as $callback) {
        $callback = $this->callableResolver->getCallableFromDefinition($parent_state->prepareCallback($callback));
        $callback($form_state, $dialog['element'], $parent_state);
      }

      $parent_form = $form_builder->rebuildForm($parent_state->getBuildInfo()['form_id'], $parent_state, $parent_form);

      // Replace only the form. Its surrounding wrappers are still in the DOM
      // and must not be rendered again inside themselves.
      unset($parent_form['#prefix'], $parent_form['#suffix']);
      $selector = 'form:has(input[name="form_build_id"][value="' . $parent_form['#build_id'] . '"])';
      return (new AjaxResponse())
        ->addCommand(new CloseDialogCommand($dialog['selector']))
        ->addCommand(new ReplaceCommand($selector, $parent_form));
    }
    finally {
      $request_stack->pop();
    }
  }

}
