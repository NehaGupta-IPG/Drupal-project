<?php

declare(strict_types=1);

namespace Drupal\Core\Extension\Requirement;

/**
 * Provides a Requirement value object.
 */
final class Requirement implements \ArrayAccess {

  /**
   * Creates a new Requirement.
   */
  public function __construct(
    protected null|string|\Stringable $title = NULL,
    protected null|string|\Stringable|array $value = NULL,
    protected null|string|\Stringable|array $description = NULL,
    protected RequirementSeverity $severity = RequirementSeverity::OK,
    protected ?int $weight = NULL,
  ) {}

  /**
   * Creates a new Requirement from a legacy array.
   *
   * @param array $requirement
   *   The requirement array.
   */
  public static function createFromLegacyArray(array $requirement): Requirement {
    return new Requirement(
      title: $requirement['title'] ?? NULL,
      value: $requirement['value'] ?? NULL,
      description: $requirement['description'] ?? NULL,
      severity: $requirement['severity'] ?? RequirementSeverity::OK,
      weight: $requirement['weight'] ?? NULL,
    );
  }

  /**
   * Gets the description.
   */
  public function getDescription(): null|string|\Stringable|array {
    return $this->description;
  }

  /**
   * Sets the description.
   */
  public function setDescription(null|string|\Stringable|array $description): Requirement {
    $this->description = $description;
    return $this;
  }

  /**
   * Gets the title.
   */
  public function getTitle(): null|string|\Stringable {
    return $this->title;
  }

  /**
   * Sets the title.
   */
  public function setTitle(null|string|\Stringable $title): Requirement {
    $this->title = $title;
    return $this;
  }

  /**
   * Gets the value.
   */
  public function getValue(): null|string|\Stringable|array {
    return $this->value;
  }

  /**
   * Sets the value.
   */
  public function setValue(null|string|\Stringable|array $value): Requirement {
    $this->value = $value;
    return $this;
  }

  /**
   * Gets the severity.
   */
  public function getSeverity(): RequirementSeverity {
    return $this->severity;
  }

  /**
   * Sets the requirement severity.
   */
  public function setSeverity(RequirementSeverity $severity): Requirement {
    $this->severity = $severity;
    return $this;
  }

  /**
   * Gets the weight.
   */
  public function getWeight(): ?int {
    return $this->weight;
  }

  /**
   * Sets the weight.
   */
  public function setWeight(int $weight): Requirement {
    $this->weight = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * This is for BC support only.
   */
  public function offsetExists($offset): bool {
    @trigger_error('Checking values using array access is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use the getter methods instead. See https://www.drupal.org/node/3410939', \E_USER_DEPRECATED);
    return in_array($offset, [
      'description',
      'title',
      'value',
      'severity',
      'weight',
    ], TRUE);
  }

  /**
   * {@inheritdoc}
   *
   * This is for BC support only.
   */
  public function offsetGet($offset): mixed {
    @trigger_error(sprintf('Getting \'%s\' using array access is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use the getter methods instead. See https://www.drupal.org/node/3410939', $offset), \E_USER_DEPRECATED);
    return match ($offset) {
      'description' => $this->getDescription(),
      'title' => $this->getTitle(),
      'value' => $this->getValue(),
      'severity' => $this->getSeverity(),
      'weight' => $this->getWeight(),
      default => NULL,
    };
  }

  /**
   * {@inheritdoc}
   *
   * This is for BC support only.
   */
  public function offsetSet($offset, $value): void {
    @trigger_error(sprintf('Setting \'%s\' using array access is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use the setter methods instead. See https://www.drupal.org/node/3410939', $offset), \E_USER_DEPRECATED);
    switch ($offset) {
      case 'description':
        $this->description = $value;
        break;

      case 'title':
        $this->title = $value;
        break;

      case 'value':
        $this->value = $value;
        break;

      case 'severity':
        $this->severity = $value;
        break;

      case 'weight':
        $this->weight = $value;
    }
  }

  /**
   * {@inheritdoc}
   *
   * This is for BC support only.
   */
  public function offsetUnset($offset): void {
    @trigger_error('Unsetting values using array access is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use the setter methods instead. See https://www.drupal.org/node/3410939', \E_USER_DEPRECATED);
    switch ($offset) {
      case 'description':
        $this->description = NULL;
        break;

      case 'title':
        $this->title = NULL;
        break;

      case 'value':
        $this->value = NULL;
        break;

      case 'severity':
        $this->severity = RequirementSeverity::OK;
        break;

      case 'weight':
        $this->weight = NULL;
    }
  }

}
