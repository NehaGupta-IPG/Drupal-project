<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests deprecations of the media library field widget.
 */
#[CoversClass(MediaLibraryWidget::class)]
#[Group('media_library')]
#[IgnoreDeprecations]
class MediaLibraryDeprecationsTest extends KernelTestBase {

  /**
   * Tests deprecation message.
   */
  public function testOptionalParametersDeprecation(): void {
    $this->expectDeprecation('Calling Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget without the $link_generator argument is deprecated in drupal:11.3.0 and it will be required in drupal:12.0.0. See https://www.drupal.org/node/3337223');
    new MediaLibraryWidget(
      '',
      '',
      $this->prophesize(BaseFieldDefinition::class)->reveal(),
      [],
      [],
      $this->container->get('entity_type.manager'),
      $this->container->get('current_user'),
      $this->container->get('module_handler'),
    );
  }

}
