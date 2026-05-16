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
use Drupal\file\FileUrlType;
use Drupal\file\Trait\UrlSuggestionTrait;
use Drupal\image\ImageDerivativeUtilities;

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

  use UrlSuggestionTrait;

  /**
   * The image style entity storage.
   *
   * @var \Drupal\image\ImageStyleStorageInterface
   */
  protected $imageStyleStorage;

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    EntityTypeManagerInterface $entity_type_manager,
    protected AccountInterface $currentUser,
    protected readonly ImageDerivativeUtilities $imageDerivativeUtilities,
    protected ?FileUrlGeneratorInterface $fileUrlGenerator = NULL,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->imageStyleStorage = $entity_type_manager->getStorage('image_style');
    if ($fileUrlGenerator === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $file_url_generator argument is deprecated in drupal:11.2.0 and it will be required in drupal:12.0.0. See https://www.drupal.org/node/3410078', E_USER_DEPRECATED);
      $this->fileUrlGenerator = \Drupal::service('file_url_generator');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'show_link_as' => FileUrlType::Relative->value,
      'image_style' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element = $this->showLinkAs($element, $this->fieldDefinition->getName());

    unset($element['image_link'], $element['image_loading']);

    $image_styles = $this->imageDerivativeUtilities->styleOptions(FALSE);
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

    $image_styles = $this->imageDerivativeUtilities->styleOptions(FALSE);
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

    $summary[] = (FileUrlType::tryFrom($this->getSetting('show_link_as')) === FileUrlType::Absolute) ? $this->t('Absolute URL') : $this->t('Relative URL');

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

    $absolute_link = FileUrlType::tryFrom($this->getSetting('show_link_as')) === FileUrlType::Absolute;
    /** @var \Drupal\image\ImageStyleInterface $image_style */
    $image_style = $this->imageStyleStorage->load($this->getSetting('image_style'));
    /** @var \Drupal\file\FileInterface[] $images */
    foreach ($images as $delta => $image) {

      $image_uri = $image->getFileUri();

      if ($image_style) {
        $image_url = $image_style->buildUrl($image_uri);
        $url = $absolute_link ? $image_url : $this->fileUrlGenerator->transformRelative($image_url);
      }
      else {
        $url = $absolute_link ? $this->fileUrlGenerator->generateAbsoluteString($image_uri) : $this->fileUrlGenerator->generateString($image_uri);
      }

      // Add cacheability metadata from the image and image style.
      $cacheability = CacheableMetadata::createFromObject($image);
      if ($image_style) {
        $cacheability->addCacheableDependency(CacheableMetadata::createFromObject($image_style));
      }

      $elements[$delta] = ['#markup' => $url];
      $cacheability->applyTo($elements[$delta]);

      if ($absolute_link) {
        array_push($elements[$delta]['#cache']['contexts'], 'url.site');
      }
    }
    return $elements;
  }

}
