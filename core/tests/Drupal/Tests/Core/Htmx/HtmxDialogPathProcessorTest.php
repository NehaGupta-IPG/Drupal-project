<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Htmx;

use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Htmx\HtmxDialogPathProcessor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests HTMX dialog path processing.
 */
#[CoversClass(HtmxDialogPathProcessor::class)]
#[Group('Htmx')]
class HtmxDialogPathProcessorTest extends UnitTestCase {

  /**
   * Tests inbound HTMX dialog path processing.
   */
  #[DataProvider('providerProcessInbound')]
  public function testProcessInbound(string $path, string $expected_path, ?string $expected_wrapper_format): void {
    $request = Request::create($path);
    $processor = new HtmxDialogPathProcessor();

    $processed_path = $processor->processInbound($path, $request);

    $this->assertSame($expected_path, $processed_path);
    $this->assertSame($expected_wrapper_format, $request->query->get(MainContentViewSubscriber::WRAPPER_FORMAT));
  }

  /**
   * Inbound paths and expected results.
   */
  public static function providerProcessInbound(): array {
    return [
      'Non-HTMX dialog path' => [
        '/node/1',
        '/node/1',
        NULL,
      ],
      'Dialog path with nested remainder' => [
        '/htmx/dialog/some-text/a/b/c',
        '/a/b/c',
        'drupal_htmx_dialog.some-text',
      ],
      'Dialog path without remainder' => [
        '/htmx/dialog/off_canvas',
        '/htmx/dialog/off_canvas',
        NULL,
      ],
      'Missing dialog type' => [
        '/htmx/dialog/',
        '/htmx/dialog/',
        NULL,
      ],
      'Empty dialog type with remainder' => [
        '/htmx/dialog//a/b/c',
        '/htmx/dialog//a/b/c',
        NULL,
      ],
    ];
  }

}

