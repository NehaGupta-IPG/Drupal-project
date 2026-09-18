<?php

namespace Drupal\node\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormSubmitterInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Contains a form for saving a node or switching its preview view mode.
 *
 * @internal
 */
class NodePreviewForm extends FormBase {

  public function __construct(
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected FormBuilderInterface $formBuilder,
    protected FormSubmitterInterface $formSubmitter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_display.repository'),
      $container->get('tempstore.private'),
      $container->get('form_builder'),
      $container->get('form_submitter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_preview_form_select';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The node being previewed.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EntityInterface $node = NULL) {
    $view_mode = $node->preview_view_mode;

    $query_options = ['query' => ['uuid' => $node->uuid()]];
    $query = $this->getRequest()->query;
    if ($query->has('destination')) {
      $query_options['query']['destination'] = $query->get('destination');
    }

    $form['backlink'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to content editing'),
      '#url' => $node->isNew() ? Url::fromRoute('entity.node.add_form', ['node_type' => $node->bundle()]) : $node->toUrl('edit-form'),
      '#options' => ['attributes' => ['class' => ['node-preview-backlink']]] + $query_options,
    ];

    $form['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#submit' => ['::save'],
    ];

    // Always show full as an option, even if the display is not enabled.
    $view_mode_options = ['full' => $this->t('Full')] + $this->entityDisplayRepository->getViewModeOptionsByBundle('node', $node->bundle());

    // Unset view modes that are not used in the front end.
    unset($view_mode_options['default']);
    unset($view_mode_options['rss']);

    $form['uuid'] = [
      '#type' => 'value',
      '#value' => $node->uuid(),
    ];

    $form['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('View mode'),
      '#options' => $view_mode_options,
      '#default_value' => $view_mode,
      '#attributes' => [
        'data-drupal-autosubmit' => TRUE,
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Switch'),
      '#attributes' => [
        'class' => ['js-hide'],
      ],
    ];

    return $form;
  }

  /**
   * Saves the node using the submission handlers from the node form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function save(array &$form, FormStateInterface $form_state): void {
    $uuid = $form_state->getValue('uuid');
    $store = $this->tempStoreFactory->get('node_preview');
    $node_form_state = $store->get($uuid);

    if (!$node_form_state instanceof FormStateInterface) {
      $this->messenger()->addError($this->t('The preview has expired. Return to content editing and preview the content again.'));
      $form_state->setRedirectUrl($form['backlink']['#url']);
      return;
    }

    $node_form_object = $node_form_state->getFormObject();
    $node_form = $this->formBuilder->getForm($node_form_object);
    $save_button = $node_form['actions']['submit'];

    // Re-submit the original node form so form alters and form-level submit
    // handlers, such as the menu link submit handler, are not bypassed.
    $node_form_state->setRebuild(FALSE);
    $node_form_state->setTriggeringElement($save_button);
    $node_form_state->setSubmitHandlers($save_button['#submit']);
    $this->formSubmitter->executeSubmitHandlers($node_form, $node_form_state);

    if ($node_form_state->getErrors() || $node_form_state->isRebuilding()) {
      $store->set($uuid, $node_form_state);
      $this->messenger()->addError($this->t('The content could not be saved. Return to content editing to correct the errors.'));
      $form_state->setRedirectUrl($form['backlink']['#url']);
      return;
    }

    $form_state->setIgnoreDestination($node_form_state->getIgnoreDestination());
    $redirect = $node_form_state->getRedirect();
    if ($redirect instanceof RedirectResponse) {
      $form_state->setResponse($redirect);
    }
    elseif ($redirect instanceof Url) {
      $form_state->setRedirectUrl($redirect);
    }
    elseif ($redirect === FALSE) {
      $form_state->disableRedirect();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $route_parameters = [
      'node_preview' => $form_state->getValue('uuid'),
      'view_mode_id' => $form_state->getValue('view_mode'),
    ];

    $options = [];
    $query = $this->getRequest()->query;
    if ($query->has('destination')) {
      $options['query']['destination'] = $query->get('destination');
      $query->remove('destination');
    }
    $form_state->setRedirect('entity.node.preview', $route_parameters, $options);
  }

}
