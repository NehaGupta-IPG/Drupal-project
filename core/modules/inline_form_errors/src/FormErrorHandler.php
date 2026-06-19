<?php

namespace Drupal\inline_form_errors;

use Drupal\Core\Form\FormElementHelper;
use Drupal\Core\Form\FormErrorHandler as CoreFormErrorHandler;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Produces inline form errors.
 */
class FormErrorHandler extends CoreFormErrorHandler {

  use StringTranslationTrait;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new FormErrorHandler.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(TranslationInterface $string_translation, RendererInterface $renderer, MessengerInterface $messenger) {
    $this->stringTranslation = $string_translation;
    $this->renderer = $renderer;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   *
   * @return $this
   */
  public function handleFormErrors(array &$form, FormStateInterface $form_state) {
    parent::handleFormErrors($form, $form_state);

    // Core's FormErrorHandler assigns a field-level error to every element
    // that inherits it through parent traversal (e.g. both the textarea and
    // the format selector of a text_format field, or every child item of an
    // inline entity form). Without this, the same message would be rendered
    // inline multiple times. Suppress all but the first visible occurrence of
    // each error so the message is shown inline exactly once.
    if ($form_state->getErrors()) {
      $seen = [];
      $this->suppressDuplicateInlineErrors($form, array_fill_keys(array_keys($form_state->getErrors()), TRUE), $seen);
    }

    return $this;
  }

  /**
   * Suppresses repeated inline error messages across the form.
   *
   * @param array $element
   *   The form element to process, by reference.
   * @param array $error_keys
   *   The set of form error keys, keyed by error name with TRUE values.
   * @param array $seen
   *   Error keys already assigned to a visible element, by reference.
   */
  protected function suppressDuplicateInlineErrors(array &$element, array $error_keys, array &$seen): void {
    // Process this element before its children (pre-order traversal) so the
    // message is kept on the outermost displaying element. For an inline entity
    // form this is the wrapping fieldset rather than a nested child field.
    $matched = $this->matchedErrorKey($element, $error_keys);
    if ($matched !== NULL && $this->rendersInlineMessage($element)) {
      if (isset($seen[$matched])) {
        $element['#error_no_message'] = TRUE;
      }
      else {
        $seen[$matched] = TRUE;
      }
    }

    foreach (Element::children($element) as $key) {
      if (is_array($element[$key])) {
        $this->suppressDuplicateInlineErrors($element[$key], $error_keys, $seen);
      }
    }
  }

  /**
   * Finds the error key an element resolves to through parent traversal.
   *
   * @param array $element
   *   The form element.
   * @param array $error_keys
   *   The set of form error keys, keyed by error name with TRUE values.
   *
   * @return string|null
   *   The matched error key, or NULL if the element has no displayable error.
   */
  protected function matchedErrorKey(array $element, array $error_keys): ?string {
    if (empty($element['#errors']) || !empty($element['#error_no_message']) || empty($element['#parents'])) {
      return NULL;
    }
    // Mirror the top-down parent traversal in FormState::getError().
    $parents = [];
    foreach ($element['#parents'] as $parent) {
      $parents[] = $parent;
      $key = implode('][', $parents);
      if (isset($error_keys[$key])) {
        return $key;
      }
    }
    return NULL;
  }

  /**
   * Determines whether inline_form_errors renders a message on an element.
   *
   * @param array $element
   *   The form element.
   *
   * @return bool
   *   TRUE if the element is visible and themed by a template for which
   *   inline_form_errors populates the error message.
   */
  protected function rendersInlineMessage(array $element): bool {
    if (!Element::isVisibleElement($element) || empty($element['#id'])) {
      return FALSE;
    }
    // These are the render elements for which InlineFormErrorsThemeHooks
    // populates an inline error message. This list must be kept in sync with
    // the preprocess hooks there.
    // @see \Drupal\inline_form_errors\Hook\InlineFormErrorsThemeHooks
    if (in_array($element['#type'] ?? NULL, ['details', 'fieldset'], TRUE)) {
      return TRUE;
    }
    $wrappers = $element['#theme_wrappers'] ?? [];
    foreach ($wrappers as $key => $value) {
      $wrapper = is_int($key) ? $value : $key;
      if (in_array($wrapper, ['form_element', 'datetime_wrapper'], TRUE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Loops through and displays all form errors.
   *
   * To disable inline form errors for an entire form set the
   * #disable_inline_form_errors property to TRUE on the top level of the $form
   * array:
   * @code
   * $form['#disable_inline_form_errors'] = TRUE;
   * @endcode
   * This should only be done when another appropriate accessibility strategy is
   * in place.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function displayErrorMessages(array $form, FormStateInterface $form_state) {
    // Skip generating inline form errors when opted out.
    if (!empty($form['#disable_inline_form_errors'])) {
      parent::displayErrorMessages($form, $form_state);
      return;
    }

    $error_links = [];
    $errors = $form_state->getErrors();
    // Loop through all form errors and check if we need to display a link.
    foreach ($errors as $name => $error) {
      $form_element = FormElementHelper::getElementByName($name, $form);
      $title = FormElementHelper::getElementTitle($form_element);

      // Only show links to erroneous elements that are visible.
      $is_visible_element = Element::isVisibleElement($form_element);
      // Only show links for elements that have a title themselves or have
      // children with a title.
      $has_title = !empty($title);
      // Only show links for elements with an ID.
      $has_id = !empty($form_element['#id']);

      // Do not show links to elements with suppressed messages. Most often
      // their parent element is used for inline errors.
      if (!empty($form_element['#error_no_message'])) {
        unset($errors[$name]);
      }
      elseif ($is_visible_element && $has_title && $has_id) {
        $url = Url::fromRoute('<none>', [], ['fragment' => $form_element['#id'], 'external' => TRUE]);
        $error_links[] = Link::fromTextAndUrl($title, $url)->toRenderable();
        unset($errors[$name]);
      }
    }

    // Set normal error messages for all remaining errors.
    foreach ($errors as $error) {
      $this->messenger->addError($error);
    }

    if (!empty($error_links)) {
      $render_array = [
        [
          '#markup' => $this->formatPlural(count($error_links), '1 error has been found: ', '@count errors have been found: '),
        ],
        [
          '#theme' => 'item_list',
          '#items' => $error_links,
          '#context' => ['list_style' => 'comma-list'],
        ],
      ];
      $message = $this->renderer->renderInIsolation($render_array);
      $this->messenger->addError($message);
    }
  }

}
