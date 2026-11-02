<?php

namespace Drupal\file\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageDerivativeUtilities;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'file_video' formatter.
 */
#[FieldFormatter(
  id: 'file_video',
  label: new TranslatableMarkup('Video'),
  description: new TranslatableMarkup('Display the file using an HTML5 video tag.'),
  field_types: [
    'file',
  ],
)]
class FileVideoFormatter extends FileMediaFormatterBase {

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
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('entity_field.manager'),
      $container->get('module_handler'),
      $container->get('entity_type.manager')->getStorage('file')
    );
  }

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected AccountInterface $currentUser,
    protected EntityStorageInterface $imageStyleStorage,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected ?ModuleHandlerInterface $moduleHandler = NULL,
    protected ?EntityStorageInterface $fileStorage = NULL,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    // The last two arguments are optional to avoid a BC break; they become
    // required next major. Deprecate omission and fall back to the container.
    if ($this->moduleHandler === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $moduleHandler argument is deprecated in drupal:11.5.0 and will be required in drupal:12.0.0. See https://www.drupal.org/project/drupal/issues/3604036', E_USER_DEPRECATED);
      $this->moduleHandler = \Drupal::moduleHandler();
    }
    if ($this->fileStorage === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $fileStorage argument is deprecated in drupal:11.5.0 and will be required in drupal:12.0.0. See https://www.drupal.org/project/drupal/issues/3604036', E_USER_DEPRECATED);
      $this->fileStorage = \Drupal::entityTypeManager()->getStorage('file');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getMediaType() {
    return 'video';
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'muted' => FALSE,
      'playsinline' => FALSE,
      'width' => 640,
      'height' => 480,
      'poster' => '',
      'poster_image_style' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $fields = $this->entityFieldManager->getFieldDefinitions($form['#entity_type'], $form['#bundle']);

    // Eligible poster fields are image fields, plus media reference fields when
    // the media module is enabled, kept optional to avoid a media dependency.
    $poster_fields = [];
    $media_enabled = $this->moduleHandler->moduleExists('media');
    foreach ($fields as $field) {
      if ($field->getType() == 'image') {
        $poster_fields[$field->getName()] = $field->getLabel();
      }
      elseif ($media_enabled && $field->getType() === 'entity_reference' && $field->getSetting('target_type') === 'media') {
        $poster_fields[$field->getName()] = $field->getLabel();
      }
    }
    $image_styles = \Drupal::service(ImageDerivativeUtilities::class)->styleOptions();
    $image_styles_description_link = Link::fromTextAndUrl(
      $this->t('Configure Image Styles'),
      Url::fromRoute('entity.image_style.collection')
    );

    return parent::settingsForm($form, $form_state) + [
      'muted' => [
        '#title' => $this->t('Muted'),
        '#type' => 'checkbox',
        '#default_value' => $this->getSetting('muted'),
      ],
      'playsinline' => [
        '#title' => $this->t('Plays Inline'),
        '#type' => 'checkbox',
        '#default_value' => $this->getSetting('playsinline'),
      ],
      'width' => [
        '#type' => 'number',
        '#title' => $this->t('Width'),
        '#default_value' => $this->getSetting('width'),
        '#size' => 5,
        '#maxlength' => 5,
        '#field_suffix' => $this->t('pixels'),
        // A width of zero pixels would make this video invisible.
        '#min' => 1,
      ],
      'height' => [
        '#type' => 'number',
        '#title' => $this->t('Height'),
        '#default_value' => $this->getSetting('height'),
        '#size' => 5,
        '#maxlength' => 5,
        '#field_suffix' => $this->t('pixels'),
        // A height of zero pixels would make this video invisible.
        '#min' => 1,
      ],
      'poster' => [
        '#type' => 'select',
        '#title' => $this->t('Poster field'),
        '#description' => $this->t('An image field, or a media reference field whose media has an image, to use as the source of the poster attribute.'),
        '#default_value' => $this->getSetting('poster'),
        '#options' => $poster_fields,
        '#empty_option' => $this->t('- None -'),
      ],
      'poster_image_style' => [
        '#type' => 'select',
        '#title' => $this->t('Poster field image style'),
        '#default_value' => $this->getSetting('poster_image_style'),
        '#options' => $image_styles,
        '#empty_option' => $this->t('None (original image)'),
        '#description' => $image_styles_description_link->toRenderable() + [
          '#access' => $this->currentUser->hasPermission('administer image styles'),
        ],
        '#states' => [
          'visible' => [
            ':input[name$="[settings][poster]"]' => ['filled' => TRUE],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Muted: %muted', ['%muted' => $this->getSetting('muted') ? $this->t('yes') : $this->t('no')]);
    $summary[] = $this->t('Plays Inline: %playsinline', ['%playsinline' => $this->getSetting('playsinline') ? $this->t('yes') : $this->t('no')]);

    $summary[] = $this->t('Size: %width x %height pixels', [
      '%width' => $this->getSetting('width'),
      '%height' => $this->getSetting('height'),
    ]);
    if (!empty($this->getSetting('poster'))) {
      $summary[] = $this->t('Poster field: %poster', ['%poster' => $this->getSetting('poster')]);
      $summary[] = $this->t('Poster image style: %poster_image_style', ['%poster_image_style' => $this->getSetting('poster_image_style') ?: $this->t('None (original image)')]);
    }
    if (!empty($this->getSetting('transcript'))) {
      $summary[] = $this->t('Transcript field: %transcript', ['%transcript' => $this->getSetting('transcript')]);
    }

    if ($width = $this->getSetting('width')) {
      $summary[] = $this->t('Width: %width pixels', [
        '%width' => $width,
      ]);
    }

    if ($height = $this->getSetting('height')) {
      $summary[] = $this->t('Height: %height pixels', [
        '%height' => $height,
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $items->getEntity();

    if (!empty($this->getSetting('poster')) && !$entity->get($this->getSetting('poster'))->isEmpty()) {
      $poster_file_item = $entity->get($this->getSetting('poster'))[0];

      $referenced = $poster_file_item->entity;
      $poster_file_entity = NULL;
      if ($referenced instanceof FileInterface) {
        // The selected field references the poster file directly (image field).
        $poster_file_entity = $referenced;
      }
      elseif ($referenced instanceof MediaInterface) {
        // Depend on the media now so a later non-image to image change
        // invalidates the host render cache on both success and bail-out paths.
        CacheableMetadata::createFromRenderArray($elements)
          ->merge(CacheableMetadata::createFromObject($referenced))
          ->applyTo($elements);
        // Loading the source file directly bypasses media access control, so
        // check 'view' access and bubble its cacheability before resolving it.
        $access = $referenced->access('view', $this->currentUser, TRUE);
        CacheableMetadata::createFromRenderArray($elements)
          ->merge(CacheableMetadata::createFromObject($access))
          ->applyTo($elements);
        if ($access->isAllowed()) {
          // Use the media translation matching the display language so a
          // per-translation poster source field value is honored.
          if ($referenced instanceof TranslatableInterface && $referenced->hasTranslation($langcode)) {
            $referenced = $referenced->getTranslation($langcode);
          }
          // Resolve its source file and accept it only when it is an image.
          $source = $referenced->getSource();
          if ($source !== NULL) {
            $source_value = $source->getSourceFieldValue($referenced);
            if ($source_value !== NULL) {
              $file = $this->fileStorage->load($source_value);
              if ($file instanceof FileInterface && str_starts_with((string) $file->getMimeType(), 'image/')) {
                $poster_file_entity = $file;
              }
            }
          }
        }
      }

      // Bail out when no usable image poster could be resolved (for example a
      // non-image media reference).
      if ($poster_file_entity === NULL) {
        return $elements;
      }

      // Set the entity in the correct language for display.
      if ($poster_file_entity instanceof TranslatableInterface && $poster_file_entity->hasTranslation($langcode)) {
        $poster_file_entity = $poster_file_entity->getTranslation($langcode);
      }

      $poster_image_style_id = $this->getSetting('poster_image_style');
      if (!empty($poster_image_style_id)) {
        // With imagecache selected:
        $poster_image_style = $this->imageStyleStorage->load($poster_image_style_id);
        CacheableMetadata::createFromRenderArray($elements)
          ->merge(CacheableMetadata::createFromObject($poster_image_style))
          ->applyTo($elements);
        $poster_url = $poster_image_style->buildUrl($poster_file_entity->getFileUri());
      }
      else {
        // Without imagecache:
        $poster_url = $poster_file_entity->createFileUrl();
      }
      CacheableMetadata::createFromRenderArray($elements)
        ->merge(CacheableMetadata::createFromObject($poster_file_entity))
        ->applyTo($elements);

      $poster_attributes = new Attribute();
      $poster_attributes->setAttribute('poster', $poster_url);
      $elements[0]['#attributes']->merge($poster_attributes);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareAttributes(array $additional_attributes = []) {
    $attributes = parent::prepareAttributes(['muted', 'playsinline']);
    if (($width = $this->getSetting('width'))) {
      $attributes->setAttribute('width', $width);
    }
    if (($height = $this->getSetting('height'))) {
      $attributes->setAttribute('height', $height);
    }
    return $attributes;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $style_id = $this->getSetting('poster_image_style');
    /** @var \Drupal\image\ImageStyleInterface $style */
    if ($style_id && $style = ImageStyle::load($style_id)) {
      // If this formatter uses a valid image style to display the image, add
      // the image style configuration entity as dependency of this formatter.
      $dependencies[$style->getConfigDependencyKey()][] = $style->getConfigDependencyName();
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies): bool {
    $changed = parent::onDependencyRemoval($dependencies);
    $style_id = $this->getSetting('poster_image_style');
    /** @var \Drupal\image\ImageStyleInterface $style */
    if ($style_id && $style = ImageStyle::load($style_id)) {
      if (!empty($dependencies[$style->getConfigDependencyKey()][$style->getConfigDependencyName()])) {
        $replacement_id = $this->imageStyleStorage->getReplacementId($style_id);
        // If the storage provides a valid replacement, swap the image style and
        // signal that the formatter plugin settings were updated.
        if (!empty($replacement_id) && ImageStyle::load($replacement_id)) {
          $this->setSetting('poster_image_style', $replacement_id);
          $changed = TRUE;
        }
      }
    }
    return $changed;
  }

}
