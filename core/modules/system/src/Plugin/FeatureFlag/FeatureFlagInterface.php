<?php

declare(strict_types=1);

namespace Drupal\system\Plugin\FeatureFlag;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface for Feature Flag plugins.
 */
interface FeatureFlagInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

}
