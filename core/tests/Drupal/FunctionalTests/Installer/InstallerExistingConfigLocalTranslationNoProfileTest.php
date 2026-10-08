<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies that a local translation source is honored without a profile.
 */
#[Group('Installer')]
#[RunTestsInSeparateProcesses]
class InstallerExistingConfigLocalTranslationNoProfileTest extends InstallerExistingConfigLocalTranslationTest {

  /**
   * {@inheritdoc}
   */
  protected $profile = FALSE;

}
