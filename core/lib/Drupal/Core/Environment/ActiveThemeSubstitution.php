<?php

declare(strict_types=1);

namespace Drupal\Core\Environment;

use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Substitutes the active theme.
 */
class ActiveThemeSubstitution implements SubstitutionInterface {

  use SubstitutionApplyTrait;

  /**
   * Constructs a new active theme substitution.
   *
   * @param \Drupal\Core\Theme\ThemeInitializationInterface $themeInitialization
   *   The theme initialization service.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager service.
   */
  public function __construct(
    protected readonly ThemeInitializationInterface $themeInitialization,
    protected readonly ThemeManagerInterface $themeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function executeInEnvironment(EnvironmentParameter $param, callable $callback): mixed {
    $originalTheme = $this->themeManager->getActiveTheme();

    $overrideThemeName = (string) $param->value;
    if ($overrideThemeName !== $originalTheme->getName()) {
      $overrideTheme = $this->themeInitialization->initTheme($overrideThemeName);
      $this->themeManager->setActiveTheme($overrideTheme);
    }

    try {
      return $callback();
    }
    finally {
      $currentTheme = $this->themeManager->getActiveTheme();
      if ($originalTheme->getName() !== $currentTheme->getName()) {
        $this->themeManager->setActiveTheme($originalTheme);
      }
    }
  }

}
