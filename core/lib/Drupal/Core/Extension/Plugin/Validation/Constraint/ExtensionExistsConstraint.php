<?php

declare(strict_types = 1);

namespace Drupal\Core\Extension\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that the value is the name of an installed extension.
 */
#[Constraint(
  id: 'ExtensionExists',
  label: new TranslatableMarkup('Extension exists', [], ['context' => 'Validation'])
)]
class ExtensionExistsConstraint extends SymfonyConstraint {

  /**
   * The type of extension to look for. Can be 'module' or 'theme'.
   *
   * @var string
   */
  public string $type;

  public function __construct(
    mixed $options = NULL,
    ?string $type = NULL,
    public string $moduleNotInstalledMessage = "Module '@name' is not installed.",
    public string $themeNotInstalledMessage = "Theme '@name' is not installed.",
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
    $this->type = $type ?? $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): ?string {
    return 'type';
  }

  /**
   * Allows to access deprecated/removed properties.
   */
  public function __get(string $name): mixed {
    if ($name === 'moduleMessage') {
      @trigger_error('The moduleMessage property is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Use moduleNotFoundMessage or moduleNotInstalledMessage instead. See https://www.drupal.org/node/3536194', E_USER_DEPRECATED);
      return $this->moduleNotInstalledMessage;
    }
    if ($name === 'themeMessage') {
      @trigger_error('The themeMessage property is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Use themeNotFoundMessage or themeNotInstalledMessage instead. See https://www.drupal.org/node/3536194', E_USER_DEPRECATED);
      return $this->themeNotInstalledMessage;
    }
    return parent::__get($name);
  }

}
