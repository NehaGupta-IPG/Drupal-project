<?php

declare(strict_types=1);

namespace Drupal\navigation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form for configuring navigation blocks.
 *
 * @internal
 */
final class LayoutForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId(): string {
    return 'navigation_layout';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'navigation_layout';
  }

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * Constructs a new LayoutForm.
   */
  public function __construct(protected AccountInterface $currentUser, protected ?LayoutTempstoreRepositoryInterface $layoutTempstoreRepository = NULL) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('layout_builder.tempstore_repository', ContainerInterface::NULL_ON_INVALID_REFERENCE)
    );
  }

  /**
   * Handles switching the configuration type selector.
   *
   * @return array
   *   An associative array containing the structure of the form.
   */
  public function enableEditMode($form, FormStateInterface $form_state): array {
    if ($form_state::hasAnyErrors()) {
      return $form;
    }

    $this->handleFormElementsVisibility($form);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface|string|null $section_storage = NULL) {
    if (!$section_storage instanceof SectionStorageInterface) {
      $section_storage = NULL;
    }

    if (!$this->layoutTempstoreRepository) {
      $this->messenger()->addWarning($this->t('To customize the navigation blocks, layout builder needs to be enabled.'));
      return $form;
    }

    $form['#prefix'] = '<div id="js-config-form-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#attributes']['class'][] = 'layout-builder-form';
    $this->sectionStorage = $section_storage;

    $form['layout_builder'] = [
      '#type' => 'layout_builder',
      '#section_storage' => $section_storage,
    ];
    $form['#attached']['library'][] = 'navigation/navigation.layoutBuilder';

    $form['actions'] = [
      'enable_edit_mode' => [
        '#type' => 'submit',
        '#value' => $this->t('Enable edit mode'),
        '#name' => 'enable_edit_mode',
        '#ajax' => [
          'callback' => '::enableEditMode',
          'wrapper' => 'js-config-form-wrapper',
          'effect' => 'fade',
        ],
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#name' => 'save',
      ],
    ] + $this->buildActions([]);

    $this->handleFormElementsVisibility($form, FALSE);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $user_input = $form_state->getUserInput();
    if (isset($user_input['save'])) {
      $this->save($form, $form_state);
    }
  }

  /**
   * Saves the Layout changes.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function save(array &$form, FormStateInterface $form_state): void {
    $this->sectionStorage->save();
    $this->saveTasks($form_state, new TranslatableMarkup('Saved navigation blocks'));
  }

  /**
   * Handles visibility of the form elements based on the edit mode status.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param bool $edit_mode_enabled
   *   Boolean indicating whether the Navigation layout edit mode is enabled.
   */
  protected function handleFormElementsVisibility(array &$form, bool $edit_mode_enabled = TRUE): array {
    // Edit mode elements are visible only in edit mode.
    $form['actions']['submit']['#access'] =
    $form['actions']['discard_changes']['#access'] =
    $form['actions']['preview_toggle']['#access'] =
    $form['actions']['preview_toggle']['toggle_content_preview']['#access'] =
    $form['layout_builder']['#access'] = $edit_mode_enabled;

    // Edit mode flag element is only visible when edit mode is disabled.
    $form['actions']['enable_edit_mode']['#access'] = !$edit_mode_enabled;

    return $form;
  }

  /**
   * Build the message container.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The message to display.
   * @param string $type
   *   The form type this is being attached to.
   *
   * @return array
   *   The render array.
   */
  protected function buildMessageContainer(TranslatableMarkup $message, string $type): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'layout-builder__message',
          sprintf('layout-builder__message--%s', $type),
        ],
      ],
      'message' => [
        '#theme' => 'status_messages',
        '#message_list' => ['status' => [$message]],
        '#status_headings' => [
          'status' => $this->t('Status message'),
        ],
      ],
      '#weight' => -900,
    ];
  }

  /**
   * Form submission handler.
   */
  public function redirectOnSubmit(array $form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl($this->sectionStorage->getLayoutBuilderUrl($form_state->getTriggeringElement()['#redirect']));
  }

  /**
   * Retrieves the section storage object.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The section storage for the current form.
   */
  public function getSectionStorage(): SectionStorageInterface {
    return $this->sectionStorage;
  }

  /**
   * Builds the actions for the form.
   *
   * @param array $actions
   *   The actions array to modify.
   *
   * @return array
   *   The modified actions array.
   */
  protected function buildActions(array $actions): array {
    $actions['#attributes']['role'] = 'region';
    $actions['#attributes']['aria-label'] = $this->t('Layout Builder tools');
    $actions['submit']['#value'] = $this->t('Save layout');
    $actions['#weight'] = -1000;
    $actions['discard_changes'] = [
      '#type' => 'submit',
      '#value' => $this->t('Discard changes'),
      '#submit' => ['::redirectOnSubmit'],
      '#redirect' => 'discard_changes',
    ];
    $actions['preview_toggle'] = $this->buildContentPreviewToggle();
    return $actions;
  }

  /**
   * Performs tasks that are needed during the save process.
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The message to display.
   */
  protected function saveTasks(FormStateInterface $formState, TranslatableMarkup $message): void {
    $this->layoutTempstoreRepository->delete($this->getSectionStorage());
    $this->messenger()->addStatus($message);
    $formState->setRedirectUrl($this->getSectionStorage()->getRedirectUrl());
  }

  /**
   * Builds the content preview toggle input.
   *
   * @return array
   *   The render array for the content preview toggle.
   */
  protected function buildContentPreviewToggle() {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['js-show'],
      ],
      'toggle_content_preview' => [
        '#title' => $this->t('Show content preview'),
        '#type' => 'checkbox',
        '#value' => TRUE,
        '#attributes' => [
          // Set attribute used by local storage to get content preview status.
          'data-content-preview-id' => "Drupal.layout_builder.content_preview.{$this->currentUser->id()}",
        ],
        '#id' => 'layout-builder-content-preview',
      ],
    ];
  }

}
