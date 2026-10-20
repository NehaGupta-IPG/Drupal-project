<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\image\Controller\ImageStyleDownloadController;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Tests the lock that guards image derivative generation.
 */
#[CoversMethod(ImageStyleDownloadController::class, 'deliver')]
#[Group('image')]
#[RunTestsInSeparateProcesses]
class ImageStyleDeliverLockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'image'];

  /**
   * The source image URI.
   */
  protected string $sourceUri = 'public://image-test.png';

  /**
   * The response, or exception, of a request made during another request.
   */
  protected Response|ServiceUnavailableHttpException|null $secondResponse = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'image']);
    \Drupal::service('file_system')->copy($this->root . '/core/tests/fixtures/files/image-test.png', $this->sourceUri);
  }

  /**
   * Tests that one style can build two derivatives of one source at once.
   *
   * Some image styles, such as the parametrized width style of the Canvas
   * module, build a different derivative URI per request from one style and
   * one source image.
   */
  public function testParallelDerivativesOfOneSource(): void {
    $style_100 = $this->createWidthStyle(100);
    $style_200 = $this->createWidthStyle(200);

    // While the first request generates its derivative, a second request, with
    // its own lock backend, asks for the other width.
    $style_100->beforeCreateDerivative = function () use ($style_200): void {
      try {
        $this->secondResponse = $this->deliver($this->createController($this->createLockBackend()), $style_200);
      }
      catch (ServiceUnavailableHttpException $e) {
        $this->secondResponse = $e;
      }
    };

    $first_response = $this->deliver($this->createController($this->createLockBackend()), $style_100);

    $this->assertSame(200, $first_response->getStatusCode());
    $this->assertInstanceOf(Response::class, $this->secondResponse);
    $this->assertSame(200, $this->secondResponse->getStatusCode());
    $this->assertFileExists($style_100->buildUri($this->sourceUri));
    $this->assertFileExists($style_200->buildUri($this->sourceUri));
  }

  /**
   * Tests that a request waits for another request to build the derivative.
   */
  public function testWaitForDerivativeInProgress(): void {
    $style = $this->createStyle();
    $derivative_uri = $style->buildUri($this->sourceUri);

    // Another request holds the lock and builds the derivative while this
    // request waits.
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(FALSE);
    $lock->expects($this->once())
      ->method('wait')
      ->willReturnCallback(function () use ($style, $derivative_uri): bool {
        $style->createDerivative($this->sourceUri, $derivative_uri);
        return FALSE;
      });
    $lock->expects($this->never())->method('release');

    $response = $this->deliver($this->createController($lock), $style);
    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Tests that a request builds the derivative if the other request failed.
   */
  public function testBuildDerivativeAfterOtherRequestFails(): void {
    $style = $this->createStyle();

    // The other request releases the lock without building the derivative.
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->exactly(2))
      ->method('acquire')
      ->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $lock->expects($this->once())->method('wait')->willReturn(FALSE);
    $lock->expects($this->once())->method('release');

    $response = $this->deliver($this->createController($lock), $style);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertFileExists($style->buildUri($this->sourceUri));
  }

  /**
   * Tests the response when the other request still holds the lock.
   *
   * @param bool $partial_derivative
   *   Whether the other request has written part of the derivative.
   */
  #[DataProvider('providerServiceUnavailableWhileLockHeld')]
  public function testServiceUnavailableWhileLockHeld(bool $partial_derivative): void {
    $style = $this->createStyle();
    $derivative_uri = $style->buildUri($this->sourceUri);

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->willReturn(FALSE);
    $lock->expects($this->once())
      ->method('wait')
      ->willReturnCallback(function () use ($partial_derivative, $derivative_uri): bool {
        if ($partial_derivative) {
          $directory = dirname($derivative_uri);
          \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
          file_put_contents($derivative_uri, 'partial');
        }
        return TRUE;
      });

    try {
      $this->deliver($this->createController($lock), $style);
      $this->fail('Expected a ServiceUnavailableHttpException.');
    }
    catch (ServiceUnavailableHttpException $e) {
      $this->assertSame(3, $e->getHeaders()['Retry-After']);
    }
  }

  /**
   * Data provider for testServiceUnavailableWhileLockHeld().
   */
  public static function providerServiceUnavailableWhileLockHeld(): array {
    return [
      'no derivative' => [FALSE],
      'partial derivative' => [TRUE],
    ];
  }

  /**
   * Creates and saves an image style.
   *
   * @return \Drupal\image\ImageStyleInterface
   *   The image style.
   */
  protected function createStyle(): ImageStyleInterface {
    $style = ImageStyle::create([
      'name' => 'plain',
      'label' => 'Plain',
    ]);
    $style->addImageEffect([
      'id' => 'image_scale',
      'weight' => 0,
      'data' => ['width' => 100, 'height' => NULL, 'upscale' => TRUE],
    ]);
    $style->save();
    return $style;
  }

  /**
   * Creates an image style that puts the width in the derivative URI.
   *
   * @param int $width
   *   The derivative width.
   *
   * @return \Drupal\Tests\image\Kernel\ParametrizedWidthImageStyle
   *   The image style.
   */
  protected function createWidthStyle(int $width): ParametrizedWidthImageStyle {
    $style = new ParametrizedWidthImageStyle(['name' => 'test', 'label' => 'Test'], 'image_style');
    $style->width = $width;
    $style->addImageEffect([
      'id' => 'image_scale',
      'weight' => 0,
      'data' => ['width' => $width, 'height' => NULL, 'upscale' => TRUE],
    ]);
    return $style;
  }

  /**
   * Creates a lock backend with its own lock ID, like a separate request.
   *
   * @return \Drupal\Core\Lock\LockBackendInterface
   *   The lock backend.
   */
  protected function createLockBackend(): LockBackendInterface {
    return new DatabaseLockBackend(\Drupal::database());
  }

  /**
   * Creates an image style download controller.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   *
   * @return \Drupal\image\Controller\ImageStyleDownloadController
   *   The controller.
   */
  protected function createController(LockBackendInterface $lock): ImageStyleDownloadController {
    return new ImageStyleDownloadController(
      $lock,
      \Drupal::service('image.factory'),
      \Drupal::service('stream_wrapper_manager'),
      \Drupal::service('file_system'),
    );
  }

  /**
   * Requests the derivative of the source image.
   *
   * @param \Drupal\image\Controller\ImageStyleDownloadController $controller
   *   The controller.
   * @param \Drupal\image\ImageStyleInterface $style
   *   The image style.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  protected function deliver(ImageStyleDownloadController $controller, ImageStyleInterface $style): Response {
    $request = Request::create('/', 'GET', [
      'file' => 'image-test.png',
      ImageStyleInterface::TOKEN => $style->getPathToken($this->sourceUri),
    ]);
    return $controller->deliver($request, 'public', $style, 'public');
  }

}
