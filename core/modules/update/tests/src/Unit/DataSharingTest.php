<?php

declare(strict_types=1);

namespace Drupal\Tests\update\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\update\DataSharing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the data sharing policy helpers.
 */
#[CoversClass(DataSharing::class)]
#[Group('update')]
class DataSharingTest extends UnitTestCase {

  /**
   * Tests the value in effect across the stored and notice period states.
   */
  #[DataProvider('providerTestEffectiveValue')]
  public function testEffectiveValue(?bool $stored, ?int $started_ago, ?bool $expected_domain, ?bool $expected_site_mail): void {
    $config = $this->getConfigFactoryStub([
      'update.settings' => ['data_sharing' => ['domain' => $stored, 'site_mail' => $stored]],
    ])->get('update.settings');
    $time = $this->createStub(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1000000);
    $state = $this->createStub(StateInterface::class);
    $state->method('get')->willReturn($started_ago === NULL ? NULL : 1000000 - $started_ago);

    $this->assertSame($expected_domain, DataSharing::effectiveValue('domain', $config, $state, $time));
    $this->assertSame($expected_site_mail, DataSharing::effectiveValue('site_mail', $config, $state, $time));
  }

  /**
   * Provides test data for testEffectiveValue().
   */
  public static function providerTestEffectiveValue(): array {
    return [
      'stored TRUE' => [TRUE, NULL, TRUE, TRUE],
      'stored FALSE' => [FALSE, NULL, FALSE, FALSE],
      'undecided, no notice start' => [NULL, NULL, NULL, NULL],
      'undecided, inside the notice period' => [NULL, DataSharing::NOTICE_PERIOD - 1, NULL, NULL],
      'undecided, notice period ended' => [
        NULL,
        DataSharing::NOTICE_PERIOD,
        DataSharing::DEFAULT_DOMAIN,
        DataSharing::DEFAULT_SITE_MAIL,
      ],
    ];
  }

}
