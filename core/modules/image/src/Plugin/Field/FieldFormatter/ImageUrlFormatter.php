<?php

namespace Drupal\image\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'image_url' formatter.
 */
#[FieldFormatter(
  id: 'image_url',
  label: new TranslatableMarkup('URL to image'),
  field_types: [
    'image',
  ],
)]
class ImageUrlFormatter extends ImageFormatterBase {

  /**
   * The image style entity storage.
   *
   * @var \Drupal\image\ImageStyleStorageInterface
   */
  protected $imageStyleStorage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs an ImageFormatter object.
   *
   * @param string $plugin_id
   *   The plugin ID for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->imageStyleStorage = $entity_type_manager->getStorage('image_style');
    $this->currentUser = $current_user;
    $this->fileUrlGenerator = $file_url_generator;
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('current_user'),
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'absolute_url' => FALSE,
      'image_style' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['absolute_url'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render as absolute url'),
      '#description' => $this->t('If checked, links will be rendered as absolute urls.'),
      '#default_value' => $this->getSetting('absolute_url'),
    ];

    unset($element['image_link'], $element['image_loading']);

    $image_styles = image_style_options(FALSE);
    $description_link = Link::fromTextAndUrl(
      $this->t('Configure Image Styles'),
      Url::fromRoute('entity.image_style.collection')
    );
    $element['image_style'] = [
      '#title' => $this->t('Image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style'),
      '#empty_option' => $this->t('None (original image)'),
      '#options' => $image_styles,
      '#description' => $description_link->toRenderable() + [
        '#access' => $this->currentUser->hasPermission('administer image styles'),
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $image_styles = image_style_options(FALSE);
    // Unset possible 'No defined styles' option.
    unset($image_styles['']);
    // Styles could be lost because of enabled/disabled modules that defines
    // their styles in code.
    $image_style_setting = $this->getSetting('image_style');
    if (isset($image_styles[$image_style_setting])) {
      $summary[] = $this->t('Image style: @style', ['@style' => $image_styles[$image_style_setting]]);
    }
    else {
      $summary[] = $this->t('Original image');
    }

    $summary[] = $this->getSetting('absolute_url') ? $this->t('Rendered as absolute url') : $this->t('Rendered as relative url');

    return array_merge($summary, parent::settingsSummary());
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    if (empty($images = $this->getEntitiesToView($items, $langcode))) {
      // Early opt-out if the field is empty.
      return $elements;
    }

    /** @var \Drupal\image\ImageStyleInterface $image_style */
    $image_style = $this->imageStyleStorage->load($this->getSetting('image_style'));
    /** @var \Drupal\file\FileInterface[] $images */
    foreach ($images as $delta => $image) {
      $image_uri = $image->getFileUri();
      if ($image_style) {
        $image_uri = $this->fileUrlGenerator->transformRelative($image_style->buildUri($image_uri));
      }
      $url = $this->fileUrlGenerator->generateAbsoluteString($image_uri);

      // Generate absolute url for the image.
      if (!$this->getSetting('absolute_url')) {
        $url = $this->fileUrlGenerator->generateString($url);
      }

      // Add cacheability metadata from the image and image style.
      $cacheability = CacheableMetadata::createFromObject($image);
      if ($image_style) {
        $cacheability->addCacheableDependency(CacheableMetadata::createFromObject($image_style));
      }

      $elements[$delta] = ['#markup' => $url];
      $cacheability->applyTo($elements[$delta]);

      if ($this->getSetting('absolute_url')) {
        array_push($elements[$delta]['#cache']['contexts'], 'url.site');
      }
    }
    return $elements;
  }

}
