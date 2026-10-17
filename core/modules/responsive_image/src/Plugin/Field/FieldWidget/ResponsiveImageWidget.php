<?php

declare(strict_types=1);

namespace Drupal\responsive_image\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'responsive_image_image' widget.
 */
#[FieldWidget(
  id: 'responsive_image_image',
  label: new TranslatableMarkup('Responsive Image'),
  field_types: ['image'],
)]
class ResponsiveImageWidget extends ImageWidget {

  /**
   * The responsive image style entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $responsiveImageStyleStorage;

  /**
   * Constructs an ResponsiveImageWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $element_info
   *   The element info manager service.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $responsive_image_style_storage
   *   The responsive image style storage.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ElementInfoManagerInterface $element_info, ImageFactory $image_factory, EntityStorageInterface $responsive_image_style_storage) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $element_info, $image_factory);
    $this->responsiveImageStyleStorage = $responsive_image_style_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('element_info'),
      $container->get('image.factory'),
      $container->get('entity_type.manager')->getStorage('responsive_image_style')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    unset($settings['preview_image_style']);
    $settings['preview_responsive_image_style'] = NULL;
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    unset($element['preview_image_style']);

    $responsive_image_options = [];
    $responsive_image_styles = $this->responsiveImageStyleStorage->loadMultiple();
    uasort($responsive_image_styles, ResponsiveImageStyle::sort(...));
    if (!empty($responsive_image_styles)) {
      foreach ($responsive_image_styles as $machine_name => $responsive_image_style) {
        if ($responsive_image_style->hasImageStyleMappings()) {
          $responsive_image_options[$machine_name] = $responsive_image_style->label();
        }
      }
    }

    $element['preview_responsive_image_style'] = [
      '#title' => $this->t('Preview responsive image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('preview_responsive_image_style') ?: NULL,
      '#options' => $responsive_image_options,
      '#empty_option' => '<' . $this->t('no preview') . '>',
      '#description' => $this->t('The preview responsive image will be shown while editing the content.'),
      '#weight' => 15,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $responsive_image_style_id = $this->getSetting('preview_responsive_image_style');
    if ($responsive_image_style_id && $responsive_image_style = $this->responsiveImageStyleStorage->load($responsive_image_style_id)) {
      $preview_responsive_image_style = $this->t('Preview responsive image style: @style', ['@style' => $responsive_image_style->label()]);
    }
    else {
      $preview_responsive_image_style = $this->t('No preview');
    }

    $summary[0] = $preview_responsive_image_style;

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Add properties needed by process() method.
    $element['#preview_responsive_image_style'] = $this->getSetting('preview_responsive_image_style');
    return $element;
  }

  /**
   * Form API callback: Processes an responsive_image_image field element.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);
    if (isset($element['preview'])) {
      if ($element['#preview_responsive_image_style']) {
        unset($element['preview']['style_name']);
        $element['preview']['#theme'] = 'responsive_image';
        $element['preview']['#responsive_image_style_id'] = $element['#preview_responsive_image_style'];
      }
      else {
        unset($element['preview']);
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $responsive_image_style_id = $this->getSetting('preview_responsive_image_style');
    /** @var \Drupal\responsive_image\ResponsiveImageStyleInterface $responsive_image_style */
    if ($responsive_image_style_id && $responsive_image_style = $this->responsiveImageStyleStorage->load($responsive_image_style_id)) {
      // Add the responsive image style as dependency.
      $dependencies[$responsive_image_style->getConfigDependencyKey()][] = $responsive_image_style->getConfigDependencyName();
    }
    return $dependencies;
  }

}
