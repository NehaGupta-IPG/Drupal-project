<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Form;

use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\Core\Form\ConfirmFormHelper
 * @group Form
 */
class ConfirmFormHelperTest extends UnitTestCase {

  /**
   * Tests the cancel link title.
   *
   * @covers ::buildCancelLink
   */
  public function testCancelLinkTitle(): void {
    $cancel_text = 'Cancel text';
    $form = $this->createMock('Drupal\Core\Form\ConfirmFormInterface');
    $form->expects($this->any())
      ->method('getCancelText')
      ->willReturn($cancel_text);

    $link = ConfirmFormHelper::buildCancelLink($form, new Request());
    $this->assertSame($cancel_text, $link['#title']);
    $this->assertSame(['contexts' => ['url.query_args:destination']], $link['#cache']);
  }

  /**
   * Tests a cancel link route.
   *
   * @covers ::buildCancelLink
   */
  public function testCancelLinkRoute(): void {
    $route_name = 'foo_bar';
    $cancel_route = new Url($route_name);
    $form = $this->createMock('Drupal\Core\Form\ConfirmFormInterface');
    $form->expects($this->any())
      ->method('getCancelUrl')
      ->willReturn($cancel_route);
    $link = ConfirmFormHelper::buildCancelLink($form, new Request());
    $this->assertEquals(Url::fromRoute($route_name), $link['#url']);
    $this->assertSame(['contexts' => ['url.query_args:destination']], $link['#cache']);
  }

  /**
   * Tests a cancel link route with parameters.
   *
   * @covers ::buildCancelLink
   */
  public function testCancelLinkRouteWithParams(): void {
    $expected = Url::fromRoute('foo_bar.baz', ['baz' => 'banana'], ['absolute' => TRUE]);
    $form = $this->createMock('Drupal\Core\Form\ConfirmFormInterface');
    $form->expects($this->any())
      ->method('getCancelUrl')
      ->willReturn($expected);
    $link = ConfirmFormHelper::buildCancelLink($form, new Request());
    $this->assertEquals($expected, $link['#url']);
    $this->assertSame(['contexts' => ['url.query_args:destination']], $link['#cache']);
  }

  /**
   * Tests a cancel link route with a URL object.
   *
   * @covers ::buildCancelLink
   */
  public function testCancelLinkRouteWithUrl(): void {
    $cancel_route = new Url(
      'foo_bar.baz', [
        'baz' => 'banana',
      ],
      [
        'absolute' => TRUE,
      ]
    );
    $form = $this->createMock('Drupal\Core\Form\ConfirmFormInterface');
    $form->expects($this->any())
      ->method('getCancelUrl')
      ->willReturn($cancel_route);
    $link = ConfirmFormHelper::buildCancelLink($form, new Request());
    $this->assertSame($cancel_route, $link['#url']);
    $this->assertSame(['contexts' => ['url.query_args:destination']], $link['#cache']);
  }

  /**
   * Tests a cancel link provided by the destination.
   *
   * @covers ::buildCancelLink
   * @dataProvider providerTestCancelLinkDestination
   */
  public function testCancelLinkDestination($destination, $base_path): void {
    $query = ['destination' => $destination];
    $form = $this->createMock('Drupal\Core\Form\ConfirmFormInterface');

    $path_validator = $this->createMock('Drupal\Core\Path\PathValidatorInterface');
    $container_builder = new ContainerBuilder();
    $container_builder->set('path.validator', $path_validator);
    \Drupal::setContainer($container_builder);

    $url = Url::fromRoute('test_route');
    $path_validator->expects($this->atLeastOnce())
      ->method('getUrlIfValidWithoutAccessCheck')
      ->with('baz')
      ->willReturn($url);

    $server['SCRIPT_FILENAME'] = "/some/docroot{$base_path}/index.php";
    $server['SCRIPT_NAME'] = "{$base_path}/index.php";
    $server['REQUEST_URI'] = "{$base_path}/some/confirmation";
    $request = new Request($query, [], [], [], [], $server);

    $link = ConfirmFormHelper::buildCancelLink($form, $request);
    $this->assertSame($url, $link['#url']);
    $this->assertSame(['contexts' => ['url.query_args:destination']], $link['#cache']);
  }

  /**
   * Provides test data for testCancelLinkDestination().
   */
  public static function providerTestCancelLinkDestination() {
    $data = [];
    $data[] = ['baz', ''];
    $data[] = ['/baz', ''];
    $data[] = ['baz', '/base/path'];
    $data[] = ['/base/path/baz', '/base/path'];
    return $data;
  }

}
