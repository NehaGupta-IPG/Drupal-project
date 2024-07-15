<?php

declare(strict_types = 1);

namespace Drupal\Core\Extension\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a given extension exists.
 */
class ExtensionExistsConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected ThemeHandlerInterface $themeHandler;

  /**
   * Constructs a ExtensionExistsConstraintValidator object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   * @param \Drupal\Core\Extension\ThemeExtensionList $themeExtensionList
   *   The theme extension list.
   * @param \Drupal\Core\Extension\ProfileExtensionList $profileExtensionList
   *   The profile extension list.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    ThemeHandlerInterface $theme_handler,
    protected readonly ModuleExtensionList $moduleExtensionList,
    protected readonly ThemeExtensionList $themeExtensionList,
    protected readonly ProfileExtensionList $profileExtensionList,
  ) {
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('theme_handler'),
      $container->get('extension.list.module'),
      $container->get('extension.list.theme'),
      $container->get(ProfileExtensionList::class)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $extension_name, Constraint $constraint): void {
    $variables = ['@name' => $extension_name];
    $must_be_installed = $constraint->mustBeInstalled;

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
        if ($must_be_installed) {
          if (!$this->moduleHandler->moduleExists($extension_name)) {
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
        if ($must_be_installed) {
          if (!$this->themeHandler->themeExists($extension_name)) {
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
