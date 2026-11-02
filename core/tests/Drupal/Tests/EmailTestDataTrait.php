<?php

declare(strict_types=1);

// cspell:ignore مستخدم رسيل السعودية fsqu Fullwidth Halfwidth

namespace Drupal\Tests;

// cspell:ignore UASG

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

  /**
   * Provides the historical UASG examples and expected transport addresses.
   *
   * The Khmer example has a 66-octet local part and exceeds the SMTP limit.
   *
   * @see https://www.drupal.org/files/issues/2022-05-10/3045516-24-Email-Address-Internationalization.patch
   * @see https://www.rfc-editor.org/rfc/rfc5321#section-4.5.3.1.1
   */
  public static function uasgEnvelopeProvider(): array {
    // cspell:disable
    return [
      'Long ASCII TLD' => [
        'email-test@universal-acceptance-test.international',
        TRUE,
        'email-test@universal-acceptance-test.international',
      ],
      'Short ASCII TLD' => [
        'email-test@universal-acceptance-test.icu',
        TRUE,
        'email-test@universal-acceptance-test.icu',
      ],
      'Arabic' => [
        'تجربة-بريد-الكتروني@تجربة-القبول-الشامل.موريتانيا',
        TRUE,
        'تجربة-بريد-الكتروني@xn-----ctdbabcfhu9c2b9l1acccr4c.xn--mgbah1a3hjkrd',
      ],
      'Armenian' => [
        'Էլփոստ-թեստ@համընդհանուր-ընկալում-թեստ.հայ',
        TRUE,
        'Էլփոստ-թեստ@xn-----ccdabplsdm5a8agd1djzdc1bg8izbl3ci.xn--y9a3aq',
      ],
      'Bengali' => [
        'ই-মেইল-পরীক্ষা@সর্বজনীন-স্বীকৃতির-পরীক্ষা.ভারত',
        TRUE,
        'ই-মেইল-পরীক্ষা@xn-----lyfb5a2czaawme6bic0ibg1jmneh6dvnhm.xn--45brj9c',
      ],
      'Cyrillic' => [
        'почта-тест@универсальное-принятие-тест.москва',
        TRUE,
        'почта-тест@xn-----6kchpbbbtfa6avfdmtlhmordcb6v9c.xn--80adxhks',
      ],
      'Devanagari' => [
        'ईमेल-परीक्षण@सार्वभौमिक-स्वीकृति-परीक्षण.संगठन',
        TRUE,
        'ईमेल-परीक्षण@xn-----lnfbb8fe3cvkui0de0bcg5hxagsg7d5lwail.xn--i1b6b1a6a2e',
      ],
      'Georgian' => [
        'ფოსტის-ტესტი@უნივერსალური-თავსობადობის-ტესტი.გე',
        TRUE,
        'ფოსტის-ტესტი@xn-----7hkbafbpdibeyjdicx7a4ad8ccvihc0acci.xn--node',
      ],
      'Greek' => [
        'ηλεκτρονικό-μήνυμα-δοκιμή@καθολική-αποδοχή-δοκιμή.ευ',
        TRUE,
        'ηλεκτρονικό-μήνυμα-δοκιμή@xn-----18bbbncyc7alhcdhg2a5bgbdp8n.xn--qxa6a',
      ],
      'Gujarati' => [
        'ઇમેઇલ-પરીક્ષણ@સાર્વત્રિક-સ્વીકૃતિ-પરીક્ષણ.ભારત',
        TRUE,
        'ઇમેઇલ-પરીક્ષણ@xn-----ilgbb8fbd9cxcbg0de0bcg5hxagsg7dvncfl.xn--gecrj9c',
      ],
      'Gurmukhi' => [
        'ਈਮੇਲ-ਪਰਖ@ਸਰਵਵਿਆਪਕ-ਪ੍ਰਵਾਨਗੀ-ਪਰਖ.ਭਾਰਤ',
        TRUE,
        'ਈਮੇਲ-ਪਰਖ@xn-----87f4dhe2jncd1dfe3dag5b8hl4a1y.xn--s9brj9c',
      ],
      'Hangul' => [
        '이메일테스트@다국어도메인이용환경테스트.한국',
        TRUE,
        '이메일테스트@xn--289a1ml9kwza93v32mygfc5bguceb581u3zduop.xn--3e0b707e',
      ],
      'Hebrew' => [
        'מבחן-דואר-אלקטרוני@מבחן-קבלה-אוניברסלי.קום',
        TRUE,
        'מבחן-דואר-אלקטרוני@xn-----wldbbcskm0ab3agqrxt2h2a.xn--9dbq2a',
      ],
      'Hiragana' => [
        'めーるてすと@どこでもつかえる.みんな',
        TRUE,
        'めーるてすと@xn--r8jfx2eok1pxc.xn--q9jyb4c',
      ],
      'Kannada' => [
        'ಇ-ಅಂಚೆ-ಪರೀಕ್ಷೆ@ಸಾರ್ವತ್ರಿಕ-ಸ್ವೀಕಾರಾರ್ಹತೆ-ಪರೀಕ್ಷೆ.ಭಾರತ',
        TRUE,
        'ಇ-ಅಂಚೆ-ಪರೀಕ್ಷೆ@xn-----euhbb9fd3c7bbdad3de4bbgx2gjbp2ak6mg6ocfhi.xn--2scrj9c',
      ],
      'Katakana' => [
        'メールテスト@ユニバーサルアクセプタンス.クラウド',
        TRUE,
        'メールテスト@xn--cck0aqsjy3fvb2dyh8bvfze.xn--gckr3f0f',
      ],
      'Lao' => [
        'ອີເມວ-ທົດລອງ@ສາກົນ-ການຍອມຮັບ-ທົດລອງ.ລາວ',
        TRUE,
        'ອີເມວ-ທົດລອງ@xn-----k1ib5a8a0cxancm7fsc2c9bgl8cje9pna.xn--q7ce6a',
      ],
      'Latin' => [
        'Email-test@Universales-Akzeptanz-Test.vermögensberatung',
        TRUE,
        'Email-test@universales-akzeptanz-test.xn--vermgensberatung-pwb',
      ],
      'Malayalam' => [
        'ഇമെയിൽ-പരിശോധന@സാർവത്രിക-സ്വീകാര്യതാ-പരിശോധന.ഭാരതം',
        TRUE,
        'ഇമെയിൽ-പരിശോധന@xn-----d5hb6edwiq1chde3dexuf2iidmno00a7aff26k.xn--rvc1e0am3e',
      ],
      'Oriya' => [
        'ଇମେଇଲ-ଟେଷ୍ଟ@ଯୁନିଭରସାଲ-ଏକସେପ୍ଟନ୍ସ-ଟେଷ୍ଟ.ଭାରତ',
        TRUE,
        'ଇମେଇଲ-ଟେଷ୍ଟ@xn-----zvgy4cba9des3awp2a7fged9hqa4b9jg2lcf.xn--3hcrj9c',
      ],
      'Sinhala' => [
        'ඉ-තැපැල්-පිරික්සුම@විශ්ව-සම්මුති-පිරික්සුම.ලංකා',
        TRUE,
        'ඉ-තැපැල්-පිරික්සුම@xn-----thi4erbvaez7bal2ah8geg4kjcb1bk.xn--fzc2c9e2c',
      ],
      'Tamil' => [
        'மின்னஞ்சல்-சோதனை@பொது-ஏற்பு-சோதனை.சிங்கப்பூர்',
        TRUE,
        'மின்னஞ்சல்-சோதனை@xn-----y6g9b6be9abd3euje9gqay1a.xn--clchc0ea0b2g2a9gcd',
      ],
      'Telugu' => [
        'ఇమెయిల్-పరీక్ష@యూనివర్సల్-ఆమోదం-పరీక్ష.భారత్',
        TRUE,
        'ఇమెయిల్-పరీక్ష@xn-----thhp1fxelv6agmhs4b3bf0j4ax3qlbcl.xn--fpcrj9c3d',
      ],
      'Thai' => [
        'อีเมลทดสอบ@ยูเอทดสอบ.ไทย',
        TRUE,
        'อีเมลทดสอบ@xn--l3cfk3a5bpd5gxc.xn--o3cw4h',
      ],
      'Simplified Chinese' => [
        '电子邮件测试@普遍适用测试.我爱你',
        TRUE,
        '电子邮件测试@xn--tkvs6ms8gqpywye3ma.xn--6qq986b3xl',
      ],
      'Traditional Chinese' => [
        '電子郵件測試@普遍適用測試.台灣',
        TRUE,
        '電子郵件測試@xn--tkv55oi7fsxx1xg2e.xn--kpry57d',
      ],
      'Ethiopic' => [
        'ኢሜይል-ሙከራ@ሁለንአቀፍ-ተቀባይነት-ሙከራ.com',
        TRUE,
        'ኢሜይል-ሙከራ@xn-----d8k2a6f2e7fb92ame8bxu4b0i7es1cr0c.com',
      ],
      'Khmer local part exceeds 64 octets' => [
        'សារអេឡិកត្រូនិកសាកល្បង@ការសាកល្បងទទួលយកជាអន្តរជាតិ.com',
        FALSE,
        'សារអេឡិកត្រូនិកសាកល្បង@xn--i2eaapqa9cbnayg2eikge9d9c4vckfz1l9wma.com',
      ],
      'Myanmar' => [
        'အီးမေးလ်စမ်းသပ်ချက်@အလုံးစုံလက်ခံမှုစမ်းသပ်ချက်.com',
        TRUE,
        'အီးမေးလ်စမ်းသပ်ချက်@xn--nidacarc6i4abyb3a4a1rbe6icd7al1bgdcy5e.com',
      ],
      'Thaana' => [
        'އީމޭލު-ޓެސްޓު@ދުނިޔެ-ގަބޫލުކުރާ-ޓެސްޓު.com',
        TRUE,
        'އީމޭލު-ޓެސްޓު@xn-----1nefem5athz6abd8wtah6aibgkrn1h.com',
      ],
      'Tibetan' => [
        'གློག་འཕྲིན་བརྟག་དཔྱད@ཡོངས་ཁྱབ་ངོས་ལེན་བརྟག་དཔྱད.com',
        TRUE,
        'གློག་འཕྲིན་བརྟག་དཔྱད@xn--nbdaaaa43akakd8katrtf0o1ao4cf63b7ah26finra.com',
      ],
      'ASCII local part with Hebrew TLD' => [
        'email-test@universal-acceptance-test.קום',
        TRUE,
        'email-test@universal-acceptance-test.xn--9dbq2a',
      ],
      'Accented Latin mailbox' => [
        'email-épreuve@épreuve-acceptation-universelle.org',
        TRUE,
        'email-épreuve@xn--preuve-acceptation-universelle-9wc.org',
      ],
      'Uppercase accented local part' => [
        'Email-Épreuve@épreuve-acceptation-universelle.org',
        TRUE,
        'Email-Épreuve@xn--preuve-acceptation-universelle-9wc.org',
      ],
      'Decomposed domain' => [
        'email-épreuve@épreuve-acceptation-universelle.org',
        TRUE,
        'email-épreuve@xn--preuve-acceptation-universelle-9wc.org',
      ],
      'Decomposed local part' => [
        'email-épreuve@épreuve-acceptation-universelle.org',
        TRUE,
        'email-épreuve@xn--preuve-acceptation-universelle-9wc.org',
      ],
      'Chinese ideographic separator' => [
        '电子邮件测试@普遍适用测试。我爱你',
        TRUE,
        '电子邮件测试@xn--tkvs6ms8gqpywye3ma.xn--6qq986b3xl',
      ],
      'Arabic ASCII and Unicode labels' => [
        'تجربة-بريد-الكتروني@xn-----ctdbabcfhu9c2b9l1acccr4c.موريتانيا',
        TRUE,
        'تجربة-بريد-الكتروني@xn-----ctdbabcfhu9c2b9l1acccr4c.xn--mgbah1a3hjkrd',
      ],
      'Arabic Unicode and ASCII labels' => [
        'تجربة-بريد-الكتروني@تجربة-القبول-الشامل.xn--mgbah1a3hjkrd',
        TRUE,
        'تجربة-بريد-الكتروني@xn-----ctdbabcfhu9c2b9l1acccr4c.xn--mgbah1a3hjkrd',
      ],
      'Arabic ASCII labels' => [
        'تجربة-بريد-الكتروني@xn-----ctdbabcfhu9c2b9l1acccr4c.xn--mgbah1a3hjkrd',
        TRUE,
        'تجربة-بريد-الكتروني@xn-----ctdbabcfhu9c2b9l1acccr4c.xn--mgbah1a3hjkrd',
      ],
      'ASCII local part with Arabic ASCII labels' => [
        'email-test@xn-----ctdbabcfhu9c2b9l1acccr4c.xn--mgbah1a3hjkrd',
        TRUE,
        'email-test@xn-----ctdbabcfhu9c2b9l1acccr4c.xn--mgbah1a3hjkrd',
      ],
    ];
    // cspell:enable
  }

  /**
   * Provides historical UASG mailbox validity expectations.
   */
  public static function uasgMailboxProvider(): array {
    return array_map(static fn (array $case) => array_slice($case, 0, 2), self::uasgEnvelopeProvider());
  }

  /**
   * Provides all issue and historical mailbox examples.
   */
  public static function allMailboxProvider(): array {
    return [...self::internationalizedMailboxProvider(), ...self::uasgMailboxProvider()];
  }

  /**
   * Provides separator input and the expected normalized mailbox.
   */
  public static function normalizedDomainMailboxProvider(): array {
    // The second local part includes a decomposed accent and a Unicode dot.
    // Both must remain unchanged: mapping applies only after the final @.
    $cases = [];
    foreach (['ASCII' => '.', 'Ideographic' => '。', 'Fullwidth' => '．', 'Halfwidth' => '｡'] as $label => $separator) {
      foreach (['ASCII local' => 'Person', 'Unicode local' => "Te\u{0301}st。用户"] as $local_label => $local) {
        $cases[$label . ' ' . $local_label] = [
          $local . '@例子' . $separator . '测试',
          $local . '@例子.测试',
        ];
      }
    }
    return $cases;
  }

}
