<?php

declare(strict_types=1);

// cspell:ignore مستخدم رسيل السعودية fsqu

namespace Drupal\Tests;

/**
 * Provides a common syntax contract for single SMTP mailboxes.
 *
 * @internal
 */
trait EmailTestDataTrait {

  /**
   * Provides the exact internationalized addresses from issue #3045516.
   */
  public static function internationalizedMailboxProvider(): array {
    // cspell:disable
    return [
      'Issue example 01' => ['Info1@ua-test.link', TRUE],
      'Issue example 02' => ['info2@ua-test.technology', TRUE],
      'Issue example 03' => ['info3@普遍接受-测试.top', TRUE],
      'Issue example 04' => ['info4@ua-test.世界', TRUE],
      'Issue example 05' => ['info5@普遍接受-测试.世界', TRUE],
      'Issue example 06' => ['uasg.tech@डाटामेल.भारत', TRUE],
      'Issue example 07' => ['info5@普遍接受-测试。世界', TRUE],
      'Issue example 08' => ['Info4@ua-test.xn--rhqv96g', TRUE],
      'Issue example 09' => ['Info3@xn----f38am99bqvcd5liy1cxsg.top', TRUE],
      'Issue example 10' => ['Info5@xn----f38am99bqvcd5liy1cxsg.xn--rhqv96g', TRUE],
      'Issue example 11' => ['info6@ختبار-القبولالعالمي.top', TRUE],
      'Issue example 12' => ['user@السعودية.رسيل', TRUE],
      'Issue example 13' => ['测试1@ua-test.link', TRUE],
      'Issue example 14' => ['测试2@ua-test.technology', TRUE],
      'Issue example 15' => ['测试3@普遍接受-测试.top', TRUE],
      'Issue example 16' => ['测试4@ua-test.世界', TRUE],
      'Issue example 17' => ['测试5@普遍接受-测试.世界', TRUE],
      'Issue example 18' => ['测试5@普遍接受-测试。世界', TRUE],
      'Issue example 19' => ['测试4@ua-test.xn--rhqv96g', TRUE],
      'Issue example 20' => ['测试3@xn----f38am99bqvcd5liy1cxsg.top', TRUE],
      'Issue example 21' => ['测试5@xn----f38am99bqvcd5liy1cxsg.xn--rhqv96g', TRUE],
      'Issue example 22' => ['测试6@ختبار-القبولالعالمي.top', TRUE],
      'Issue example 23' => ['مستخدم@رسيل.السعودية', TRUE],
    ];
    // cspell:enable
  }

}
