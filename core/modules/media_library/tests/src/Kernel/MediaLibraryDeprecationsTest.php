<?php

namespace Drupal\Tests\media_library\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget;

/**
 * @coversDefaultClass \Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget
 * @group media_library
 * @group legacy
 */
class MediaLibraryDeprecationsTest extends KernelTestBase {

  /**
   * @covers ::__construct
   */
  public function testOptionalParametersDeprecation(): void {
    $this->expectDeprecation('Calling Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget::__construct without the $link_generator argument is deprecated in drupal:10.1.0 and it will be required in drupal:11.0.0. See https://www.drupal.org/node/3337223');
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
