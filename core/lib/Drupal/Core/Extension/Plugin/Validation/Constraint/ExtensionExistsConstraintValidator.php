<?php

declare(strict_types = 1);

namespace Drupal\Core\Extension\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a given extension exists.
 */
class ExtensionExistsConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    protected readonly ModuleExtensionList $moduleExtensionList,
    protected readonly ThemeExtensionList $themeExtensionList,
    protected readonly ProfileExtensionList $profileExtensionList,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(ModuleExtensionList::class),
      $container->get(ThemeExtensionList::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $extension_name, Constraint $constraint): void {
    $variables = ['@name' => $extension_name];

    switch ($constraint->type) {
      case 'module':
        // This constraint may be used to validate nullable (optional) values.
        if ($extension_name === NULL) {
          return;
        }
        // Some plugins are shipped in `core/lib`, which corresponds to the
        // special `core` extension name.
        // For example: \Drupal\Core\Menu\Plugin\Block\LocalActionsBlock.
        if ($extension_name === 'core') {
          return;
        }
        if ($constraint->mustBeInstalled) {
          if (!array_key_exists($extension_name, $this->moduleExtensionList->getAllInstalledInfo())) {
            $this->context->addViolation($constraint->moduleNotInstalledMessage, $variables);
          }
        }
        else {
          if (!$this->moduleExtensionList->exists($extension_name)) {
            $this->context->addViolation($constraint->moduleNotExistsMessage, $variables);
          }
        }
        break;

      case 'theme':
        // This constraint may be used to validate nullable (optional) values.
        if ($extension_name === NULL) {
          return;
        }
        if ($constraint->mustBeInstalled) {
          if (!array_key_exists($extension_name, $this->themeExtensionList->getAllInstalledInfo())) {
            $this->context->addViolation($constraint->themeNotInstalledMessage, $variables);
          }
        }
        else {
          if (!$this->themeExtensionList->exists($extension_name)) {
            $this->context->addViolation($constraint->themeNotExistsMessage, $variables);
          }
        }
        break;

      default:
        throw new \InvalidArgumentException("Unknown extension type: '$constraint->type'");
    }
  }

}
