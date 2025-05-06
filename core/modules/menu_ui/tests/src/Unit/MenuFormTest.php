<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_ui\Unit;

use Drupal\Core\Form\FormState;
use Drupal\menu_ui\Form\MenuForm;
use Drupal\menu_ui\Form\MenuLinkEditForm;
use Drupal\system\MenuInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\menu_ui\MenuForm
 * @group menu_ui
 */
class MenuFormTest extends UnitTestCase {

  /**
   * The menu entity mock.
   *
   * @var \Drupal\system\MenuInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $menu;

  /**
   * The MenuForm under test.
   *
   * @var \Drupal\menu_ui\Form\MenuForm|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock Menu entity.
    $this->menu = $this->createMock(MenuInterface::class);
    $this->menu->expects($this->any())->method('label')->willReturn('Test Menu');
    $this->menu->expects($this->any())->method('id')->willReturn('test_menu');
    $this->menu->expects($this->any())->method('isNew')->willReturn(TRUE);
    $this->menu->expects($this->any())->method('isLocked')->willReturn(FALSE);
    $this->menu->expects($this->any())->method('getDescription')->willReturn('Test description');
    $this->menu->expects($this->any())->method('language')->willReturn((object) ['getId' => fn() => 'en']);

    // Partial mock of MenuForm to stub translation and dependent methods.
    $this->form = $this->getMockBuilder(MenuLinkEditForm::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['t', 'menuNameExists', 'buildOverviewForm'])
      ->getMock();

    $this->form->method('t')->willReturnCallback(fn($string) => $string);
    $this->form->setEntity($this->menu);
  }

  /**
   * Tests that the machine name field accepts underscores.
   */
  public function testMachineNameFieldAllowsUnderscores(): void {
    $form_state = new FormState();
    $form = $this->form->form([], $form_state);

    $this->assertArrayHasKey('id', $form);
    $this->assertEquals('machine_name', $form['id']['#type']);
    $this->assertEquals('[^a-z0-9_-]+', $form['id']['#machine_name']['replace_pattern']);
    $this->assertEquals('_', $form['id']['#machine_name']['replace']);
    $this->assertEquals('test_menu', $form['id']['#default_value']);
  }

}
