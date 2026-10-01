<?php

namespace Drupal\Core\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Handles form errors.
 */
class FormErrorHandler implements FormErrorHandlerInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  public function __construct(
    protected RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function handleFormErrors(array &$form, FormStateInterface $form_state) {
    // After validation check if there are errors.
    if ($form_state->getErrors()) {
      if (Settings::get('enable_inline_error_handling', FALSE)) {
        // Display error messages for each element.
        $this->displayErrorMessagesInline($form, $form_state);
      }
      else {
        // Display error messages for each element.
        $this->displayErrorMessages($form, $form_state);
      }

      // Loop through and assign each element its errors.
      $this->setElementErrorsFromFormState($form, $form_state);
    }

    return $this;
  }

  /**
   * Loops through and displays all form errors.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function displayErrorMessages(array $form, FormStateInterface $form_state) {
    $errors = $form_state->getErrors();

    // Loop through all form errors and set an error message.
    foreach ($errors as $error) {
      $this->messenger()->addMessage($error, 'error');
    }
  }

  /**
   * Loops through and displays all form errors.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function displayErrorMessagesInline(array $form, FormStateInterface $form_state): void {
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
      $this->messenger()->addError($error);
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
      $this->messenger()->addError($message);
    }
  }

  /**
   * Stores errors and a list of child element errors directly on each element.
   *
   * Grouping elements like containers, details and fieldsets may
   * need error info from their child elements to be able to accessibly show
   * form error messages to a user. For example, a details element should be
   * opened when child elements have errors.
   *
   * Grouping example:
   * Assume you have a 'street' element somewhere in a form, which is displayed
   * in a details element 'address'. It might be:
   *
   * @code
   * $form['street'] = [
   *   '#type' => 'textfield',
   *   '#title' => $this->t('Street'),
   *   '#group' => 'address',
   *   '#required' => TRUE,
   * ];
   * $form['address'] = [
   *   '#type' => 'details',
   *   '#title' => $this->t('Address'),
   * ];
   * @endcode
   *
   * When submitting an empty street field, the generated error is available to
   * the different render elements like so:
   * @code
   * // The street textfield element.
   * $element = [
   *   '#errors' => {Drupal\Core\StringTranslation\TranslatableMarkup},
   *   '#children_errors' => [],
   * ];
   * // The address detail element.
   * $element = [
   *   '#errors' => null,
   *   '#children_errors' => [
   *      'street' => {Drupal\Core\StringTranslation\TranslatableMarkup}
   *   ],
   * ];
   * @endcode
   *
   * The list of child element errors of an element is an associative array. A
   * child element error is keyed with the #array_parents value of the
   * respective element. The key is formed by imploding this value with '][' as
   * glue. For example, a value ['contact_info', 'name'] becomes
   * 'contact_info][name'.
   *
   * @param array $form
   *   An associative array containing a reference to the complete structure of
   *   the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $elements
   *   An associative array containing the part of the form structure that will
   *   be processed while traversing up the tree. For recursion only; leave
   *   empty when calling this method.
   */
  protected function setElementErrorsFromFormState(array &$form, FormStateInterface $form_state, array &$elements = []) {
    // At the start of traversing up the form tree set the to be processed
    // elements to the complete form structure by reference so that we can
    // modify the original form. When processing grouped elements a reference to
    // the complete form is needed.
    if (empty($elements)) {
      // cspell:disable-next-line
      // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.VariableRedeclaration
      $elements = &$form;
    }

    // Recurse through all element children.
    foreach (Element::children($elements) as $key) {
      if (!empty($elements[$key])) {
        // Get the child by reference so that we can update the original form.
        $child = &$elements[$key];

        // Call self to traverse up the form tree. The current element's child
        // contains the next elements to be processed.
        $this->setElementErrorsFromFormState($form, $form_state, $child);

        $children_errors = [];

        // Inherit all recorded "children errors" of the direct child.
        if (!empty($child['#children_errors'])) {
          $children_errors = $child['#children_errors'];
        }

        // Additionally store the errors of the direct child itself, keyed by
        // its parent elements structure.
        if (!empty($child['#errors'])) {
          $child_parents = implode('][', $child['#array_parents']);
          $children_errors[$child_parents] = $child['#errors'];
        }

        if (!empty($elements['#children_errors'])) {
          $elements['#children_errors'] += $children_errors;
        }
        else {
          $elements['#children_errors'] = $children_errors;
        }

        // If this direct child belongs to a group populate the grouping element
        // with the children errors.
        if (!empty($child['#group'])) {
          $parents = explode('][', $child['#group']);
          $group_element = NestedArray::getValue($form, $parents);
          if (isset($group_element['#children_errors'])) {
            $group_element['#children_errors'] = $group_element['#children_errors'] + $children_errors;
          }
          else {
            $group_element['#children_errors'] = $children_errors;
          }
          NestedArray::setValue($form, $parents, $group_element);
        }
      }
    }

    // Store the errors for this element on the element directly.
    $elements['#errors'] = $form_state->getError($elements);
  }

}
