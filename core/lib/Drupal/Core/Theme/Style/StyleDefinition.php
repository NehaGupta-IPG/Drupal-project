<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Style;

use Drupal\Component\Plugin\Definition\PluginDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Template\AttributeHelper;
use Drupal\Core\Theme\Style\Enum\ModeColorScheme;
use Drupal\Core\Theme\Style\Enum\ModeContrast;
use Drupal\Core\Theme\Style\Enum\ModeReducedMotion;
use Drupal\Core\Theme\Style\Enum\Type;
use Drupal\Core\Theme\Style\Enum\Status;
use Drupal\Core\Theme\Style\Enum\Target;

/**
 * Style definition class.
 *
 * @internal
 *   This API is experimental.
 */
class StyleDefinition extends PluginDefinition implements StyleDefinitionInterface {

  /**
   * The enumeration of each option mode.
   *
   * @var array<string, class-string<\BackedEnum>>
   */
  protected const array MODES = [
    'color_scheme' => ModeColorScheme::class,
    'contrast' => ModeContrast::class,
    'reduced_motion' => ModeReducedMotion::class,
  ];

  /**
   * Style definition.
   */
  protected array $definition = [
    'id' => '',
    'enabled' => TRUE,
    'label' => '',
    'description' => '',
    'category' => 'Other',
    'provider' => '',
    'links' => [],
    'status' => Status::Stable,
    'weight' => 0,
    'type' => Type::Utility,
    'attribute' => 'class',
    'target' => Target::Any,
    'options' => [],
    'library' => '',
  ];

  /**
   * Store the processed options.
   */
  protected ?array $processedOptions;

  /**
   * Constructor.
   *
   * @param array<string, mixed> $definition
   *   The definition.
   */
  public function __construct(array $definition = []) {
    foreach ($definition as $name => $value) {
      switch ($name) {
        case 'status':
          $this->setStatus($value);
          break;

        case 'type':
          $this->setType($value);
          break;

        case 'target':
          $this->setTarget($value);
          break;

        case 'options':
          $this->setOptions($value);
          break;

        default:
          $this->definition[$name] = $value;
          break;
      }
    }

    $this->id = $this->definition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsAsOptions(): array {
    $options = [];
    foreach ($this->getProcessedOptions() as $optionId => $option) {
      $options[$optionId] = $option['label'];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessedOptions(): array {
    if (isset($this->processedOptions)) {
      return $this->processedOptions;
    }

    $attribute = $this->getAttribute();
    $target = $this->getTarget();
    $options = $this->getOptions();

    foreach ($options as $optionId => $option) {
      $options[$optionId]['value'] = $option['value'] ?? $optionId;
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $options[$optionId]['label'] = new TranslatableMarkup((string) $option['label']);
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $options[$optionId]['description'] = isset($option['description']) ? new TranslatableMarkup((string) $option['description']) : '';
      $options[$optionId]['attribute'] = $option['attribute'] ?? $attribute;
      $options[$optionId]['target'] = $option['target'] ?? $target;
      $options[$optionId]['status'] = $option['status'] ?? Status::Stable;
      $options[$optionId]['modes'] = \array_merge(static::getDefaultModes(), $option['modes'] ?? []);

      if ($options[$optionId]['status'] != Status::Stable) {
        $options[$optionId]['label'] = new TranslatableMarkup('@label (@status)', [
          '@label' => $options[$optionId]['label'],
          '@status' => $options[$optionId]['status']->label(),
        ]);
      }
    }

    $this->processedOptions = $options;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function applyOrBubbleOnAttribute(string $optionId, Attribute $attribute): void {
    $styleId = $this->id();
    $options = $this->getProcessedOptions();
    $status = $this->getStatus();

    if ($status->triggerWarning()) {
      \Drupal::logger('style')->warning('Usage of style %styleId which is in %status status.', [
        '%styleId' => $styleId,
        '%status' => $status->label(),
      ]);
    }
    if (!isset($options[$optionId])) {
      \Drupal::logger('style')->error('Invalid style option %optionId for style %styleId.', [
        '%styleId' => $styleId,
        '%optionId' => $optionId,
      ]);
      return;
    }
    elseif ($options[$optionId]['status']->triggerWarning()) {
      \Drupal::logger('style')->warning('Usage of style option %optionId which is in %status status for style %styleId.', [
        '%styleId' => $styleId,
        '%optionId' => $optionId,
        '%status' => $options[$optionId]['status']->label(),
      ]);
    }

    $type = $this->getType();
    $optionTarget = $options[$optionId]['target'];
    $optionAttribute = $options[$optionId]['attribute'];
    $optionValue = $options[$optionId]['value'];
    $attachments = [];

    if ($type == Type::Theme && $optionTarget == Target::Meta) {
      $meta = [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => $optionAttribute,
          'content' => $optionValue,
        ],
      ];
      $attachments['html_head'][] = [$meta, "style:{$styleId}:{$optionId}"];
    }
    // Target: HTML or body.
    elseif ($type == Type::Theme && $optionTarget != Target::Any) {
      $attachments['styles'][$styleId] = $optionId;
    }
    else {
      $attribute->merge(new Attribute([$optionAttribute => $optionValue]));
    }

    $styleLibrary = $this->getLibrary();
    if (!empty($styleLibrary)) {
      $attachments['library'][] = $styleLibrary;
    }

    if (!empty($attachments)) {
      $attribute->addAttachments($attachments);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applyOnAttribute(string $optionId, Attribute $attribute): void {
    $styleId = $this->id();
    $options = $this->getProcessedOptions();
    $status = $this->getStatus();

    if ($status->triggerWarning()) {
      \Drupal::logger('style')->warning('Usage of style %styleId which is in %status status.', [
        '%styleId' => $styleId,
        '%status' => $status->label(),
      ]);
    }
    if (!isset($options[$optionId])) {
      \Drupal::logger('style')->error('Invalid style option %optionId for style %styleId.', [
        '%styleId' => $styleId,
        '%optionId' => $optionId,
      ]);
      return;
    }
    elseif ($options[$optionId]['status']->triggerWarning()) {
      \Drupal::logger('style')->warning('Usage of style option %optionId which is in %status status for style %styleId.', [
        '%styleId' => $styleId,
        '%optionId' => $optionId,
        '%status' => $options[$optionId]['status']->label(),
      ]);
    }

    $type = $this->getType();
    $optionTarget = $options[$optionId]['target'];
    $optionAttribute = $options[$optionId]['attribute'];
    $optionValue = $options[$optionId]['value'];

    // Ignore type 'theme' with target 'meta' as it is too late.
    if ($type == Type::Utility || ($type == Type::Theme && $optionTarget != Target::Meta)) {
      $attribute->merge(new Attribute([$optionAttribute => $optionValue]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applyOrBubbleOnArray(string $optionId, array &$element, string $attributesKey = '#attributes'): void {
    $styleId = $this->id();
    $options = $this->getProcessedOptions();
    $status = $this->getStatus();

    if ($status->triggerWarning()) {
      \Drupal::logger('style')->warning('Usage of style %styleId which is in %status status.', [
        '%styleId' => $styleId,
        '%status' => $status->label(),
      ]);
    }
    if (!isset($options[$optionId])) {
      \Drupal::logger('style')->error('Invalid style option %optionId for style %styleId.', [
        '%styleId' => $styleId,
        '%optionId' => $optionId,
      ]);
      return;
    }
    elseif ($options[$optionId]['status']->triggerWarning()) {
      \Drupal::logger('style')->warning('Usage of style option %optionId which is in %status status for style %styleId.', [
        '%styleId' => $styleId,
        '%optionId' => $optionId,
        '%status' => $options[$optionId]['status']->label(),
      ]);
    }

    $type = $this->getType();
    $optionTarget = $options[$optionId]['target'];
    $optionAttribute = $options[$optionId]['attribute'];
    $optionValue = $options[$optionId]['value'];
    $element['#attached'] = $element['#attached'] ?? [];

    if ($type == Type::Theme && $optionTarget == Target::Meta) {
      $meta = [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => $optionAttribute,
          'content' => $optionValue,
        ],
      ];
      $element['#attached']['html_head'][] = [$meta, "style:{$styleId}:{$optionId}"];
    }
    // Target: HTML or body.
    elseif ($type == Type::Theme && $optionTarget != Target::Any) {
      $element['#attached']['styles'][$styleId] = $optionId;
    }
    elseif (!isset($element['#accept_attributes']) || $element['#accept_attributes'] === TRUE) {
      // Need special case for class if the attribute does not have any yet.
      if ($optionAttribute == 'class') {
        $optionValue = [$optionValue];
      }

      $element[$attributesKey] = $element[$attributesKey] ?? [];
      $element[$attributesKey] = AttributeHelper::mergeCollections(
        $element[$attributesKey],
        [
          $optionAttribute => $optionValue,
        ]
      );
    }

    $styleLibrary = $this->getLibrary();
    if (!empty($styleLibrary)) {
      $element['#attached']['library'][] = $styleLibrary;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition(): array {
    return $this->definition;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return $this->definition['enabled'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel($label) {
    $this->definition['label'] = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->definition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->definition['description'] = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCategory() {
    return $this->definition['category'];
  }

  /**
   * {@inheritdoc}
   */
  public function setCategory($category) {
    $this->definition['category'] = $category;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProvider(): string {
    return $this->definition['provider'];
  }

  /**
   * {@inheritdoc}
   */
  public function setProvider(string $provider) {
    $this->definition['provider'] = $provider;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLinks(): array {
    $links = [];

    foreach ($this->definition['links'] as $link) {
      if (!\is_array($link)) {
        $link = [
          'url' => $link,
        ];
      }

      $link += [
        'title' => 'External documentation',
      ];
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $link['title'] = new TranslatableMarkup($link['title']);

      $links[] = $link;
    }

    return $links;
  }

  /**
   * {@inheritdoc}
   */
  public function setLinks(array $links) {
    $this->definition['links'] = $links;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): Status {
    return $this->definition['status'];
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(Status|string $status) {
    if (is_string($status)) {
      $this->definition['status'] = Status::from($status);
    }
    else {
      $this->definition['status'] = $status;
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return $this->definition['weight'];
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight(int $weight) {
    $this->definition['weight'] = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): Type {
    return $this->definition['type'];
  }

  /**
   * {@inheritdoc}
   */
  public function setType(Type|string $type) {
    if (is_string($type)) {
      $this->definition['type'] = Type::from($type);
    }
    else {
      $this->definition['type'] = $type;
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttribute(): string {
    return $this->definition['attribute'];
  }

  /**
   * {@inheritdoc}
   */
  public function setAttribute(string $attribute) {
    $this->definition['attribute'] = $attribute;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTarget(): Target {
    return $this->definition['target'];
  }

  /**
   * {@inheritdoc}
   */
  public function setTarget(Target|string $target) {
    if (empty($target)) {
      $this->definition['target'] = Target::Any;
    }
    elseif (is_string($target)) {
      $this->definition['target'] = Target::from($target);
    }
    else {
      $this->definition['target'] = $target;
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(): array {
    return $this->definition['options'];
  }

  /**
   * {@inheritdoc}
   */
  public function setOptions(array $options) {
    // Prepare enum.
    foreach ($options as $optionId => $option) {
      if (isset($option['target']) && !($option['target'] instanceof Target)) {
        $options[$optionId]['target'] = Target::from($option['target']);
      }

      if (isset($option['status']) && !($option['status'] instanceof Status)) {
        $options[$optionId]['status'] = Status::from($option['status']);
      }

      foreach (static::MODES as $mode => $enum) {
        if (isset($option['modes'][$mode]) && !($option['modes'][$mode] instanceof $enum)) {
          $options[$optionId]['modes'][$mode] = $enum::from($option['modes'][$mode]);
        }
      }
    }

    $this->definition['options'] = $options;
    $this->processedOptions = NULL;
    return $this;
  }

  /**
   * Get the modes matching any user preference.
   *
   * @return array<string, \BackedEnum>
   *   The modes, keyed by mode name, all set to their `any` case.
   */
  protected static function getDefaultModes(): array {
    $modes = [];
    foreach (static::MODES as $mode => $enum) {
      $modes[$mode] = $enum::from('any');
    }
    return $modes;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibrary(): string {
    return $this->definition['library'];
  }

  /**
   * {@inheritdoc}
   */
  public function setLibrary(string $library) {
    $this->definition['library'] = $library;
    return $this;
  }

}
