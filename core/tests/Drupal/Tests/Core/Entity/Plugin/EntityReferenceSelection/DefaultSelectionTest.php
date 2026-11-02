<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests the deprecated target bundle form validation callback.
 */
#[CoversClass(DefaultSelection::class)]
#[Group('EntityReference')]
class DefaultSelectionTest extends UnitTestCase {

  /**
   * Tests that the deprecated callback still normalizes an empty selection.
   */
  #[IgnoreDeprecations]
  public function testValidateTargetBundlesDeprecation(): void {
    $this->expectUserDeprecationMessage('Drupal\\Core\\Entity\\Plugin\\EntityReferenceSelection\\DefaultSelection::validateTargetBundles() is deprecated in drupal:12.0.0 and is removed from drupal:13.0.0. No direct replacement is provided. Set the form value to NULL directly for bundleless entity types. For entity types with bundles, custom forms that allow removing the bundle restriction must provide an explicit choice to allow all bundles and store NULL only for that choice. Preserve [] for an empty selection, including when the last allowed bundle has been deleted, or require the selection to be corrected. See https://www.drupal.org/node/3061180');
    $element = ['#parents' => ['target_bundles']];
    $form_state = new FormState();
    $form_state->setValue('target_bundles', []);

    DefaultSelection::validateTargetBundles($element, $form_state, []);

    $this->assertNull($form_state->getValue('target_bundles'));
  }

}
