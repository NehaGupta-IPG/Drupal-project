<?php

namespace Drupal\responsive_image\Plugin\Field\FieldFormatter;

use Drupal\breakpoint\BreakpointManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;

/**
 * Plugin for responsive image formatter.
 */
#[FieldFormatter(
  id: 'responsive_image',
  label: new TranslatableMarkup('Responsive image'),
  field_types: [
    'image',
  ],
)]
class ResponsiveImageFormatter extends ImageFormatterBase {

  /**
   * The file url generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected EntityStorageInterface $responsiveImageStyleStorage,
    protected EntityStorageInterface $imageStyleStorage,
    protected LinkGeneratorInterface $linkGenerator,
    protected AccountInterface $currentUser,
    protected BreakpointManagerInterface $breakpointManager,
    ?FileUrlGeneratorInterface $fileUrlGenerator = NULL,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    if (!$fileUrlGenerator) {
      @trigger_error('Calling ResponsiveImageFormatter::__construct() without the $fileUrlGenerator argument is deprecated in drupal:11.4.0 and the $fileUrlGenerator argument will be required in drupal:12.0.0. See https://www.drupal.org/node/3291487', E_USER_DEPRECATED);
      $fileUrlGenerator = \Drupal::service('file_url_generator');
    }
    $this->fileUrlGenerator = $fileUrlGenerator;
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
      $container->get('entity_type.manager')->getStorage('responsive_image_style'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('link_generator'),
      $container->get('current_user'),
      $container->get('breakpoint.manager'),
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'responsive_image_style' => '',
      'image_link' => '',
      'image_loading' => [
        'preload' => FALSE,
        'attribute' => 'lazy',
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $responsive_image_options = [];
    $responsive_image_styles = $this->responsiveImageStyleStorage->loadMultiple();
    uasort($responsive_image_styles, '\Drupal\responsive_image\Entity\ResponsiveImageStyle::sort');
    if ($responsive_image_styles && !empty($responsive_image_styles)) {
      foreach ($responsive_image_styles as $machine_name => $responsive_image_style) {
        if ($responsive_image_style->hasImageStyleMappings()) {
          $responsive_image_options[$machine_name] = $responsive_image_style->label();
        }
      }
    }

    $elements['responsive_image_style'] = [
      '#title' => $this->t('Responsive image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('responsive_image_style') ?: NULL,
      '#required' => TRUE,
      '#options' => $responsive_image_options,
      '#description' => [
        '#markup' => $this->linkGenerator->generate($this->t('Configure Responsive Image Styles'), new Url('entity.responsive_image_style.collection')),
        '#access' => $this->currentUser->hasPermission('administer responsive image styles'),
      ],
    ];

    $image_loading = $this->getSetting('image_loading');
    $elements['image_loading'] = [
      '#type' => 'details',
      '#title' => $this->t('Image loading'),
      '#weight' => 10,
      '#description' => $this->t('Render images with page preload link or image loading attribute.'),
    ];
    $elements['image_loading']['preload'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preload'),
      '#default_value' => $image_loading['preload'] ?? FALSE,
      '#description' => $this->t('Preload to optimize the loading of late-discovered resources. Normally large or hero images below the fold. <a href=":link_preload">Learn more about preload</a> and see <a href=":link_examples">examples for images</a>. When using a responsive image style, breakpoints must be mutually exclusive: at most one <code>min-width</code>-only query (the outermost top breakpoint) and at most one <code>max-width</code>-only query (the outermost bottom breakpoint) are permitted; all other breakpoints must be bounded by both <code>min-width</code> and <code>max-width</code>, and no breakpoint may omit a width constraint entirely. Otherwise multiple image variants may download simultaneously at some viewport sizes.', [
        ':link_preload' => 'https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/rel/preload',
        ':link_examples' => 'https://html.spec.whatwg.org/multipage/semantics.html#attr-link-imagesrcset',
      ]),
    ];

    // Warning: scan all available styles so the warning can be driven
    // purely by client-side #states without a page reload. Collect IDs of every
    // style whose breakpoints are not mutually exclusive.
    $non_exclusive_style_ids = [];
    foreach ($responsive_image_styles as $candidate_id => $candidate) {
      if (!$candidate->hasImageStyleMappings()) {
        continue;
      }
      // Only inspect breakpoints that have actual style mappings.
      $breakpoints = $this->breakpointManager->getBreakpointsByGroup($candidate->getBreakpointGroup());
      $used_breakpoint_ids = array_keys($candidate->getKeyedImageStyleMappings());
      $media_queries = array_map(
        fn($bp) => $bp->getMediaQuery(),
        array_intersect_key($breakpoints, array_flip($used_breakpoint_ids)),
      );
      // When no used breakpoint carries a media query, fall back to checking
      // the media conditions embedded in any sizes attribute values.
      $non_empty = array_filter(array_map('trim', $media_queries), fn($q) => !empty($q));
      if (empty($non_empty)) {
        $media_queries = static::extractSizesConditions($candidate->getKeyedImageStyleMappings());
      }
      if (static::hasNonExclusiveBreakpoints($media_queries)) {
        $non_exclusive_style_ids[] = $candidate_id;
      }
    }
    if (!empty($non_exclusive_style_ids)) {
      $elements['image_loading']['preload_non_exclusive_warning'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        // Show when preload is checked AND the selected style is non-exclusive.
        // Multiple style IDs are evaluated as OR by the states API.
        '#states' => [
          'visible' => [
            ':input[name*="preload"]' => ['checked' => TRUE],
            ':input[name*="responsive_image_style"]' => array_map(
              fn($id) => ['value' => $id],
              $non_exclusive_style_ids,
            ),
          ],
        ],
        'message' => [
          '#markup' => $this->t('The selected responsive image style contains breakpoints that are not mutually exclusive: multiple <code>min-width</code>-only or <code>max-width</code>-only queries, or a query with no width constraint. With preload enabled, multiple image variants may download simultaneously at certain viewport sizes. Allow at most one <code>min-width</code>-only and one <code>max-width</code>-only query (the outermost top and bottom breakpoints); every other breakpoint must be bounded by both <code>min-width</code> and <code>max-width</code>.'),
        ],
      ];
    }

    $elements['image_loading']['attribute_pre_description'] = [
      '#type' => 'container',
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Lazy render images with native image loading attribute (<em>loading="lazy"</em>). This improves performance by allowing browsers to lazily load images. See <a href="@url">Lazy loading</a>.', [
          '@url' => 'https://developer.mozilla.org/en-US/docs/Web/Performance/Lazy_loading#images_and_iframes',
        ]),
        '#attributes' => ['class' => ['form-item__description']],
      ],
      '#states' => [
        'visible' => [
          ':input[name*="preload"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $loading_attribute_options = [
      'lazy' => $this->t('Lazy'),
      'eager' => $this->t('Eager'),
    ];
    $elements['image_loading']['attribute'] = [
      '#title' => $this->t('Lazy loading attribute'),
      '#type' => 'select',
      '#default_value' => $image_loading['attribute'],
      '#options' => $loading_attribute_options,
      '#description' => $this->t('Select the lazy loading attribute for images. <a href=":link">Learn more.</a>', [
        ':link' => 'https://html.spec.whatwg.org/multipage/urls-and-fetching.html#lazy-loading-attributes',
      ]),
      '#states' => [
        'visible' => [
          ':input[name*="preload"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $link_types = [
      'content' => $this->t('Content'),
      'file' => $this->t('File'),
    ];
    $elements['image_link'] = [
      '#title' => $this->t('Link image to'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_link'),
      '#empty_option' => $this->t('Nothing'),
      '#options' => $link_types,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $responsive_image_style = $this->responsiveImageStyleStorage->load($this->getSetting('responsive_image_style'));
    if ($responsive_image_style) {
      $summary[] = $this->t('Responsive image style: @responsive_image_style', ['@responsive_image_style' => $responsive_image_style->label()]);

      $link_types = [
        'content' => $this->t('Linked to content'),
        'file' => $this->t('Linked to file'),
      ];
      // Display this setting only if image is linked.
      if (isset($link_types[$this->getSetting('image_link')])) {
        $summary[] = $link_types[$this->getSetting('image_link')];
      }
    }
    else {
      $summary[] = $this->t('Select a responsive image style.');
    }

    $image_loading = $this->getSetting('image_loading');
    $summary[] = $this->t('Image loading: @attribute', [
      '@attribute' => $image_loading['preload'] ? $this->t('preload') : $image_loading['attribute'],
    ]);

    return array_merge($summary, parent::settingsSummary());
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $elements;
    }

    $url = NULL;
    // Check if the formatter involves a link.
    if ($this->getSetting('image_link') == 'content') {
      $entity = $items->getEntity();
      if (!$entity->isNew()) {
        $url = $entity->toUrl();
      }
    }
    elseif ($this->getSetting('image_link') == 'file') {
      $link_file = TRUE;
    }

    // Collect cache tags to be added for each item in the field.
    $responsive_image_style = $this->responsiveImageStyleStorage->load($this->getSetting('responsive_image_style'));
    $image_styles_to_load = [];
    $cache_tags = [];
    if ($responsive_image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $responsive_image_style->getCacheTags());
      $image_styles_to_load = $responsive_image_style->getImageStyleIds();
    }

    $image_styles = $this->imageStyleStorage->loadMultiple($image_styles_to_load);
    foreach ($image_styles as $image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $image_style->getCacheTags());
    }

    foreach ($files as $delta => $file) {
      assert($file instanceof FileInterface);
      // Link the <picture> element to the original file.
      if (isset($link_file)) {
        $url = $this->fileUrlGenerator->generate($file->getFileUri());
      }
      // Extract field item attributes for the theme function, and unset them
      // from the $item so that the field template does not re-render them.
      $item = $file->_referringItem;
      $attributes = $item->_attributes;
      unset($item->_attributes);

      $image_loading_settings = $this->getSetting('image_loading');
      if (empty($image_loading_settings['preload'])) {
        $attributes['loading'] = $image_loading_settings['attribute'];
      }

      $elements[$delta] = [
        '#theme' => 'responsive_image_formatter',
        '#item' => $item,
        '#attributes' => $attributes,
        '#responsive_image_style_id' => $responsive_image_style ? $responsive_image_style->id() : '',
        '#url' => $url,
        '#preload' => $image_loading_settings['preload'] ?? FALSE,
        '#cache' => [
          'tags' => $cache_tags,
        ],
      ];
    }
    return $elements;
  }

  /**
   * Extracts media conditions from sizes attribute in image style mappings.
   *
   * A sizes value like "(min-width: 700px) 700px, 100vw"
   * contains two explicit conditions. The trailing fallback ("100vw") has no
   * condition and is skipped. The conditions are parsed by matching balanced
   * parentheses so that compound expressions like "(not (max-width: 560px))"
   * are handled correctly.
   *
   * @param array $keyed_mappings
   *   Value of ResponsiveImageStyleInterface::getKeyedImageStyleMappings().
   *
   * @return string[]
   *   Unique media condition strings (e.g. "(min-width: 700px) 700px").
   */
  protected static function extractSizesConditions(array $keyed_mappings): array {
    $sizes = [];
    foreach ($keyed_mappings as $multipliers) {
      foreach ($multipliers as $mapping) {
        if ($mapping['image_mapping_type'] !== 'sizes' || empty($mapping['image_mapping']['sizes'])) {
          continue;
        }
        $sizes = array_merge($sizes, explode(',', $mapping['image_mapping']['sizes']));
      }
    }
    return $sizes;
  }

  /**
   * Checks whether a set of media queries is non-exclusive (cumulative).
   *
   * For preload to work correctly, at most one breakpoint may match any given
   * viewport width. A set of media queries is non-exclusive when one of the
   * following patterns is present:
   *
   * - Two or more min-width-only queries: cumulative on wider viewports — every
   *   screen wider than the lower bound matches each query, so multiple
   *   breakpoints fire simultaneously on larger screens.
   * - Two or more max-width-only queries: cumulative on smaller viewports —
   *   every screen narrower than the upper bound matches each query, so
   *   multiple breakpoints fire simultaneously on smaller screens.
   * - Any query without min-width and without max-width: not bounded by width
   *   at all (e.g. orientation or color queries) — can overlap with any
   *   width-based breakpoint.
   *
   * A single min-width-only query is allowed when at least one other query in
   * the set carries a max-width: it represents an open-ended outermost top
   * breakpoint (anything wider than X) paired with bounded siblings covering
   * lower viewports. A single max-width-only query is allowed symmetrically
   * for the outermost bottom breakpoint.
   *
   * @param string[] $media_queries
   *   Media query strings from the responsive image style's breakpoints.
   *
   * @return bool
   *   TRUE when the set is non-exclusive, FALSE otherwise.
   */
  public static function hasNonExclusiveBreakpoints(array $media_queries): bool {
    $non_empty = array_filter(array_map('trim', $media_queries), fn($q) => !empty($q));
    if (count($non_empty) <= 1) {
      return FALSE;
    }
    $min_only_count = 0;
    $max_only_count = 0;
    foreach ($non_empty as $query) {
      $has_min = str_contains($query, 'min-width');
      $has_max = str_contains($query, 'max-width');
      // Queries without any width constraint can overlap with any width-based
      // breakpoint.
      if (!$has_min && !$has_max) {
        return TRUE;
      }
      if ($has_min && !$has_max) {
        $min_only_count++;
      }
      elseif (!$has_min && $has_max) {
        $max_only_count++;
      }
    }
    // At most one open-ended top and one open-ended bottom; everything else
    // must be bounded on both sides.
    return $min_only_count > 1 || $max_only_count > 1;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $style_id = $this->getSetting('responsive_image_style');
    /** @var \Drupal\responsive_image\ResponsiveImageStyleInterface $style */
    if ($style_id && $style = ResponsiveImageStyle::load($style_id)) {
      // Add the responsive image style as dependency.
      $dependencies[$style->getConfigDependencyKey()][] = $style->getConfigDependencyName();
    }
    return $dependencies;
  }

}
