<?php

declare(strict_types=1);

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Style\StyleDefinition;
use Drupal\Core\Theme\Style\StylePluginManagerInterface;

/**
 * Provides a form element to select styles.
 *
 * Properties:
 * - #default_value: (optional) The default classes. Structured as an array
 *   with:
 *   - key: style plugin ID.
 *   - value: the selected option ID.
 * - #wrapper_type: (optional) Allows to change the wrapper type. Defaults
 *   to details.
 * - #open: (optional) Indicates whether the container should be open by
 *   default if it is a details. Defaults to FALSE.
 *
 * Usage example:
 *
 * @code
 * $form['styles'] = [
 *   '#type' => 'styles',
 *   '#title' => $this->t('Styles'),
 *   '#default_value' => [],
 * ];
 * @endcode
 */
#[FormElement('styles')]
class Styles extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#multiple' => FALSE,
      '#default_value' => [],
      '#wrapper_type' => 'details',
      '#open' => FALSE,
      '#process' => [
        [static::class, 'buildForm'],
        [static::class, 'processGroup'],
      ],
      '#element_validate' => [
        [static::class, 'validateStyles'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if (\is_array($input)
      && \array_key_exists('wrapper', $input)
      && \is_array($input['wrapper'])
      && !empty($input['wrapper'])
    ) {
      $value = static::extractSelectedStyles($input['wrapper']);
    }
    else {
      $value = $element['#default_value'] ?? [];
    }
    /** @var array $value */
    return \array_filter($value);
  }

  /**
   * Build the form element.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   * @param array $completeForm
   *   The complete form structure.
   *
   * @return array
   *   The form element.
   */
  public static function buildForm(array &$element, FormStateInterface $formState, array &$completeForm): array {
    $groupedDefinitions = static::getGroupedDefinitions($element);
    if (empty($groupedDefinitions)) {
      return $element;
    }

    $suffix = static::getAppliedSuffix();
    $multipleGroups = (\count($groupedDefinitions) == 1) ? FALSE : TRUE;
    // Open the root details if specified or if a child has an error.
    $open = (!empty($element['#open']) || !empty($element['#children_errors'])) ? TRUE : FALSE;

    $element['wrapper'] = [
      '#type' => $element['#wrapper_type'] ?? 'details',
      '#title' => $element['#title'] ?? '',
      '#description' => $element['#description'] ?? '',
      '#open' => $open,
      '#states' => $element['#states'] ?? [],
    ];

    $globalUsed = FALSE;
    foreach ($groupedDefinitions as $groupDefinitions) {
      $groupUsed = FALSE;
      $groupKey = '';
      foreach ($groupDefinitions as $definition) {
        $id = $definition->id();
        $pluginElement = static::getFormElement($definition, $element['#default_value'][$id] ?? '');

        // Check if current style is used.
        $used = FALSE;
        if (!empty($pluginElement['#default_value'])) {
          $globalUsed = TRUE;
          $groupUsed = TRUE;
          $used = TRUE;
        }

        if ($multipleGroups) {
          $groupKey = static::getMachineName($definition->getCategory());
          // Create group if it does not exist yet.
          if (!isset($element['wrapper'][$groupKey])) {
            $element['wrapper'][$groupKey] = [
              '#type' => 'details',
              '#title' => $definition->getCategory(),
            ];
          }

          $element['wrapper'][$groupKey][$id] = $pluginElement;
          if ($used) {
            $element['wrapper'][$groupKey][$id]['#title'] .= $suffix;
          }
        }
        else {
          $element['wrapper'][$id] = $pluginElement;
          if ($used) {
            $element['wrapper'][$id]['#title'] .= $suffix;
          }
        }
      }

      if (!empty($groupKey) && $groupUsed && isset($element['wrapper'][$groupKey]['#title'])) {
        $element['wrapper'][$groupKey]['#title'] .= $suffix;
      }
    }

    if ($globalUsed && !empty($element['wrapper']['#title'])) {
      $element['wrapper']['#title'] .= $suffix;
    }

    return $element;
  }

  /**
   * Form element validation handler.
   *
   * Override $form_state value using #element_validate and not #after_build
   * because sub element would recreate the structure.
   */
  public static function validateStyles(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $form_state->setValueForElement($element, $element['#value']);
  }

  /**
   * Wraps the style plugin manager.
   *
   * @return \Drupal\Core\Theme\Style\StylePluginManagerInterface
   *   The style plugin manager.
   */
  protected static function stylesManager(): StylePluginManagerInterface {
    return \Drupal::service(StylePluginManagerInterface::class);
  }

  /**
   * Get grouped plugin definitions.
   *
   * @param array $element
   *   The form element.
   *
   * @return array
   *   The grouped plugin definitions.
   */
  protected static function getGroupedDefinitions(array $element): array {
    $stylesManager = static::stylesManager();
    return $stylesManager->getGroupedDefinitions();
  }

  /**
   * Generates a machine name from a string.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $string
   *   The string to convert.
   *
   * @return string
   *   The converted string.
   *
   * @see \Drupal\Core\Block\BlockBase::getMachineNameSuggestion()
   * @see \Drupal\system\MachineNameController::transliterate()
   */
  protected static function getMachineName($string): string {
    $transliterated = \Drupal::transliteration()->transliterate($string, LanguageInterface::LANGCODE_DEFAULT, '_');
    $transliterated = \mb_strtolower($transliterated);
    $transliterated = \preg_replace('@[^a-z0-9_.]+@', '_', $transliterated);
    return $transliterated ?? '';
  }

  /**
   * Get the suffix to indicate a style is applied.
   *
   * @return string
   *   The "applied" suffix.
   */
  protected static function getAppliedSuffix(): string {
    return ' <sup>(<mark>' . new TranslatableMarkup('applied') . '</mark>)</sup>';
  }

  /**
   * Get selected styles from form values.
   *
   * Handle groups.
   *
   * @param array $formValues
   *   The form values to extract the selected classes from.
   *
   * @return array
   *   The selected values.
   */
  protected static function extractSelectedStyles(array $formValues): array {
    $selected = [];
    foreach ($formValues as $id => $value) {
      /** @var string $id */
      // Case of a group.
      if (\is_array($value)) {
        $selected = \array_merge($selected, static::extractSelectedStyles($value));
        continue;
      }

      if (empty($value)) {
        continue;
      }

      $selected[$id] = $value;
    }
    return $selected;
  }

  /**
   * Get a select or a checkbox depending on the style definition.
   *
   * @param \Drupal\Core\Theme\Style\StyleDefinition $definition
   *   The style plugin definition.
   * @param string $defaultValue
   *   The default value.
   *
   * @return array
   *   The form element for this style plugin.
   */
  protected static function getFormElement(StyleDefinition $definition, string $defaultValue): array {
    $options = $definition->getOptionsAsOptions();

    // Checkbox.
    if (\count($options) == 1) {
      $value = \array_keys($options)[0];
      $title = new TranslatableMarkup('@style_label: @style_option', [
        '@style_label' => $definition->getLabel(),
        '@style_option' => \array_shift($options),
      ]);

      return [
        '#type' => 'checkbox',
        '#title' => $title,
        '#default_value' => $defaultValue,
        '#return_value' => $value,
        '#weight' => $definition->getWeight(),
      ];
    }
    // Select.
    else {
      return [
        '#type' => 'select',
        '#title' => $definition->getLabel(),
        '#options' => $options,
        '#default_value' => $defaultValue,
        '#empty_option' => new TranslatableMarkup('- None -'),
        '#weight' => $definition->getWeight(),
      ];
    }
  }

}
