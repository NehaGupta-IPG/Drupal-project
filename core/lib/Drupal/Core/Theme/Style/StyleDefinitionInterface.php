<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Style;

use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\Style\Enum\Type;
use Drupal\Core\Theme\Style\Enum\Status;
use Drupal\Core\Theme\Style\Enum\Target;

/**
 * Interface for style definition.
 *
 * @phpstan-type StyleProcessedOptions array<string, array{
 *   value: string,
 *   label: \Drupal\Core\Entity\TranslatableInterface|string,
 *   description: \Drupal\Core\Entity\TranslatableInterface|string,
 *   attribute: string,
 *   target: \Drupal\Core\Theme\Style\Enum\Target,
 *   status: \Drupal\Core\Theme\Style\Enum\Status,
 * }>
 *
 * @internal
 *   This API is experimental.
 */
interface StyleDefinitionInterface {

  /**
   * Get options as Form API options.
   *
   * @return array
   *   Options for select form element options.
   */
  public function getOptionsAsOptions(): array;

  /**
   * Get options completed with common info and in full format.
   *
   * @return StyleProcessedOptions
   *   Processed options.
   */
  public function getProcessedOptions(): array;

  /**
   * Apply a style option depending on its type and target.
   *
   * @param string $optionId
   *   The style option ID.
   * @param \Drupal\Core\Template\Attribute $attribute
   *   The attribute object to apply style to.
   */
  public function applyOrBubbleOnAttribute(string $optionId, Attribute $attribute): void;

  /**
   * Apply a style option to an attribute object.
   *
   * For usage when attachments had already been processed.
   *
   * @param string $optionId
   *   The style option ID.
   * @param \Drupal\Core\Template\Attribute $attribute
   *   The attribute object to apply style to.
   */
  public function applyOnAttribute(string $optionId, Attribute $attribute): void;

  /**
   * Apply a style option depending on its type and target.
   *
   * @param string $optionId
   *   The style option ID.
   * @param array $element
   *   The render array to apply style to.
   * @param string $attributesKey
   *   The render array key containing the attributes.
   */
  public function applyOrBubbleOnArray(string $optionId, array &$element, string $attributesKey = '#attributes'): void;

  /**
   * Getter.
   *
   * @return array
   *   The whole definition array.
   */
  public function getDefinition(): array;

  /**
   * Getter.
   *
   * @return bool
   *   Property value.
   */
  public function isEnabled(): bool;

  /**
   * Getter.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   Property value.
   */
  public function getLabel();

  /**
   * Setter.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $label
   *   Property value.
   *
   * @return $this
   */
  public function setLabel($label);

  /**
   * Getter.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   Property value.
   */
  public function getDescription();

  /**
   * Setter.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $description
   *   Property value.
   *
   * @return $this
   */
  public function setDescription($description);

  /**
   * Getter.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   Property value.
   */
  public function getCategory();

  /**
   * Setter.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $category
   *   Property value.
   *
   * @return $this
   */
  public function setCategory($category);

  /**
   * Getter.
   *
   * @return string
   *   Property value.
   */
  public function getProvider(): string;

  /**
   * Setter.
   *
   * @param string $provider
   *   Property value.
   *
   * @return $this
   */
  public function setProvider(string $provider);

  /**
   * Getter.
   *
   * @return array
   *   Property value.
   */
  public function getLinks(): array;

  /**
   * Setter.
   *
   * @param array $links
   *   Property value.
   *
   * @return $this
   */
  public function setLinks(array $links);

  /**
   * Getter.
   *
   * @return \Drupal\Core\Theme\Style\Enum\Status
   *   Property value.
   */
  public function getStatus(): Status;

  /**
   * Setter.
   *
   * @param \Drupal\Core\Theme\Style\Enum\Status|string $status
   *   Property value.
   *
   * @return $this
   */
  public function setStatus(Status|string $status);

  /**
   * Getter.
   *
   * @return int
   *   Property value.
   */
  public function getWeight(): int;

  /**
   * Setter.
   *
   * @param int $weight
   *   Property value.
   *
   * @return $this
   */
  public function setWeight(int $weight);

  /**
   * Getter.
   *
   * @return \Drupal\Core\Theme\Style\Enum\Type
   *   Property value.
   */
  public function getType(): Type;

  /**
   * Setter.
   *
   * @param \Drupal\Core\Theme\Style\Enum\Type|string $type
   *   Property value.
   *
   * @return $this
   */
  public function setType(Type|string $type);

  /**
   * Getter.
   *
   * @return string
   *   Property value.
   */
  public function getAttribute(): string;

  /**
   * Setter.
   *
   * @param string $attribute
   *   Property value.
   *
   * @return $this
   */
  public function setAttribute(string $attribute);

  /**
   * Getter.
   *
   * @return \Drupal\Core\Theme\Style\Enum\Target
   *   Property value.
   */
  public function getTarget(): Target;

  /**
   * Setter.
   *
   * @param \Drupal\Core\Theme\Style\Enum\Target|string $target
   *   Property value.
   *
   * @return $this
   */
  public function setTarget(Target|string $target);

  /**
   * Getter.
   *
   * @return array
   *   Property value.
   */
  public function getOptions(): array;

  /**
   * Setter.
   *
   * @param array $options
   *   Property value.
   *
   * @return $this
   */
  public function setOptions(array $options);

  /**
   * Getter.
   *
   * @return string
   *   Property value.
   */
  public function getLibrary(): string;

  /**
   * Setter.
   *
   * @param string $library
   *   Property value.
   *
   * @return $this
   */
  public function setLibrary(string $library);

}
