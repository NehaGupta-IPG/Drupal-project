<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Mail;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

// cspell:ignore derp officedocument openxmlformats wordprocessingml åwesome
// cspell:ignore ÜBER

/**
 * @coversDefaultClass \Drupal\Core\Mail\MailFormatHelper
 * @group Mail
 */
class MailFormatHelperTest extends UnitTestCase {

  /**
   * Base path used in this test.
   *
   * @var string
   */
  protected $basePath = '/';

  /**
   * Base URL used in this test.
   *
   * @var string
   */
  protected $baseUrl = 'http://example.com';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $request = $this->prophesize(Request::class);
    $request->getBasePath()->willReturn($this->basePath);
    $request->getBaseUrl()->willReturn($this->baseUrl);

    $request_stack = new RequestStack();
    $request_stack->push($request->reveal());

    $container = new ContainerBuilder();
    $container->set('request_stack', $request_stack);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::htmlToText
   * @dataProvider providerHtmlToText
   */
  public function testHtmlToText($html, $text, $allowed_tags = NULL): void {
    $this->assertSame($text, MailFormatHelper::htmlToText($html, $allowed_tags));
  }

  public static function providerHtmlToText(): array {
    return [
      // @todo Trailing linefeeds should be trimmed.
      ['<a href = "https://www.drupal.org">Drupal.org</a>', "Drupal.org [1]\n\n[1] https://www.drupal.org\n"],
      // @todo Footer URLs should be absolute.
      // ["<a href = \"$this->basePath\">Homepage</a>", "Homepage [1]\n\n[1] $this->baseUrl/\n"],
      ['<address>Drupal</address>', "Drupal\n"],
      // @todo The <address> tag is currently not supported.
      ['<address>Drupal</address><address>Drupal</address>', "DrupalDrupal\n"],
      ['<b>Drupal</b>', "*Drupal*\n"],
      // @todo There should be a space between the '>' and the text.
      ['<blockquote>Drupal</blockquote>', ">Drupal\n"],
      ['<blockquote>Drupal</blockquote><blockquote>Drupal</blockquote>', ">Drupal\n>Drupal\n"],
      ['<br />Drupal<br />Drupal<br /><br />Drupal', "Drupal\nDrupal\nDrupal\n"],
      ['<br/>Drupal<br/>Drupal<br/><br/>Drupal', "Drupal\nDrupal\nDrupal\n"],
      // @todo There should be two line breaks before the paragraph.
      ['<br/>Drupal<br/>Drupal<br/><br/>Drupal<p>Drupal</p>', "Drupal\nDrupal\nDrupal\nDrupal\n\n"],
      ['<div>Drupal</div>', "Drupal\n"],
      // @todo The <div> tag is currently not supported.
      ['<div>Drupal</div><div>Drupal</div>', "DrupalDrupal\n"],
      ['<em>Drupal</em>', "/Drupal/\n"],
      ['<h1>Drupal</h1>', "======== Drupal ==============================================================\n\n"],
      ['<h1>Drupal</h1><p>Drupal</p>', "======== Drupal ==============================================================\n\nDrupal\n\n"],
      ['<h2>Drupal</h2>', "-------- Drupal --------------------------------------------------------------\n\n"],
      ['<h2>Drupal</h2><p>Drupal</p>', "-------- Drupal --------------------------------------------------------------\n\nDrupal\n\n"],
      ['<h3>Drupal</h3>', ".... Drupal\n\n"],
      ['<h3>Drupal</h3><p>Drupal</p>', ".... Drupal\n\nDrupal\n\n"],
      ['<h4>Drupal</h4>', ".. Drupal\n\n"],
      ['<h4>Drupal</h4><p>Drupal</p>', ".. Drupal\n\nDrupal\n\n"],
      ['<h5>Drupal</h5>', "Drupal\n\n"],
      ['<h5>Drupal</h5><p>Drupal</p>', "Drupal\n\nDrupal\n\n"],
      ['<h6>Drupal</h6>', "Drupal\n\n"],
      ['<h6>Drupal</h6><p>Drupal</p>', "Drupal\n\nDrupal\n\n"],
      ['<hr />Drupal<hr />', "------------------------------------------------------------------------------\nDrupal\n------------------------------------------------------------------------------\n"],
      ['<hr/>Drupal<hr/>', "------------------------------------------------------------------------------\nDrupal\n------------------------------------------------------------------------------\n"],
      ['<hr/>Drupal<hr/><p>Drupal</p>', "------------------------------------------------------------------------------\nDrupal\n------------------------------------------------------------------------------\nDrupal\n\n"],
      ['<i>Drupal</i>', "/Drupal/\n"],
      ['<p>Drupal</p>', "Drupal\n\n"],
      ['<p>Drupal</p><p>Drupal</p>', "Drupal\n\nDrupal\n\n"],
      ['<strong>Drupal</strong>', "*Drupal*\n"],
      // @todo Tables are currently not supported.
      ['<table><tr><td>Drupal</td><td>Drupal</td></tr><tr><td>Drupal</td><td>Drupal</td></tr></table>', "DrupalDrupalDrupalDrupal\n"],
      ['<table><tr><td>Drupal</td></tr></table><p>Drupal</p>', "Drupal\nDrupal\n\n"],
      // @todo The <u> tag is currently not supported.
      ['<u>Drupal</u>', "Drupal\n"],
      ['<ul><li>Drupal</li></ul>', " * Drupal\n\n"],
      ['<ul><li>Drupal <em>Drupal</em> Drupal</li></ul>', " * Drupal /Drupal/ Drupal\n\n"],
      // @todo Lines containing nothing but spaces should be trimmed.
      ['<ul><li>Drupal</li><li><ol><li>Drupal</li><li>Drupal</li></ol></li></ul>', " * Drupal\n *  1) Drupal\n    2) Drupal\n   \n\n"],
      ['<ul><li>Drupal</li><li><ol><li>Drupal</li></ol></li><li>Drupal</li></ul>', " * Drupal\n *  1) Drupal\n   \n * Drupal\n\n"],
      ['<ul><li>Drupal</li><li>Drupal</li></ul>', " * Drupal\n * Drupal\n\n"],
      ['<ul><li>Drupal</li></ul><p>Drupal</p>', " * Drupal\n\nDrupal\n\n"],
      ['<ol><li>Drupal</li></ol>', " 1) Drupal\n\n"],
      ['<ol><li>Drupal</li><li><ul><li>Drupal</li><li>Drupal</li></ul></li></ol>', " 1) Drupal\n 2)  * Drupal\n     * Drupal\n    \n\n"],
      ['<ol><li>Drupal</li><li>Drupal</li></ol>', " 1) Drupal\n 2) Drupal\n\n"],
      ['<ol>Drupal</ol>', "Drupal\n\n"],
      ['<ol><li>Drupal</li></ol><p>Drupal</p>', " 1) Drupal\n\nDrupal\n\n"],
      ['<dl><dt>Drupal</dt></dl>', "Drupal\n\n"],
      ['<dl><dt>Drupal</dt><dd>Drupal</dd></dl>', "Drupal\n    Drupal\n\n"],
      ['<dl><dt>Drupal</dt><dd>Drupal</dd><dt>Drupal</dt><dd>Drupal</dd></dl>', "Drupal\n    Drupal\nDrupal\n    Drupal\n\n"],
      ['<dl><dt>Drupal</dt><dd>Drupal</dd></dl><p>Drupal</p>', "Drupal\n    Drupal\n\nDrupal\n\n"],
      ['<dl><dt>Drupal<dd>Drupal</dl>', "Drupal\n    Drupal\n\n"],
      ['<dl><dt>Drupal</dt></dl><p>Drupal</p>', "Drupal\n\nDrupal\n\n"],
      // @todo Again, lines containing only spaces should be trimmed.
      ['<ul><li>Drupal</li><li><dl><dt>Drupal</dt><dd>Drupal</dd><dt>Drupal</dt><dd>Drupal</dd></dl></li><li>Drupal</li></ul>', " * Drupal\n * Drupal\n       Drupal\n   Drupal\n       Drupal\n   \n * Drupal\n\n"],
      // Tests malformed HTML tags.
      ['<br>Drupal<br>Drupal', "Drupal\nDrupal\n"],
      ['<hr>Drupal<hr>Drupal', "------------------------------------------------------------------------------\nDrupal\n------------------------------------------------------------------------------\nDrupal\n"],
      ['<ol><li>Drupal<li>Drupal</ol>', " 1) Drupal\n 2) Drupal\n\n"],
      ['<ul><li>Drupal <em>Drupal</em> Drupal</ul></ul>', " * Drupal /Drupal/ Drupal\n\n"],
      ['<ul><li>Drupal<li>Drupal</ol>', " * Drupal\n * Drupal\n\n"],
      ['<ul><li>Drupal<li>Drupal</ul>', " * Drupal\n * Drupal\n\n"],
      ['<ul>Drupal</ul>', "Drupal\n\n"],
      ['Drupal</ul></ol></dl><li>Drupal', "Drupal\n * Drupal\n"],
      ['<dl>Drupal</dl>', "Drupal\n\n"],
      ['<dl>Drupal</dl><p>Drupal</p>', "Drupal\n\nDrupal\n\n"],
      ['<dt>Drupal</dt>', "Drupal\n"],
      // Tests some unsupported HTML tags.
      ['<html>Drupal</html>', "Drupal\n"],
      // @todo Perhaps the contents of <script> tags should be dropped.
      ['<script type="text/javascript">Drupal</script>', "Drupal\n"],
      // A couple of tests for Unicode characters.
      ['<q>I <em>will</em> be back…</q>', "I /will/ be back…\n"],
      ['FrançAIS is ÜBER-åwesome', "FrançAIS is ÜBER-åwesome\n"],

      // Test that allowed tags are allowed.
      ['Drupal <b>Drupal</b> Drupal', "Drupal *Drupal* Drupal\n", ['b']],
      ['Drupal <h1>Drupal</h1> Drupal', "Drupal Drupal Drupal\n", ['b']],
      ['Drupal <p><em><b>Drupal</b></em><p> Drupal', "Drupal Drupal Drupal\n", ['a', 'br', 'h1']],
      ['<html><body>Drupal</body></html>', "Drupal\n", ['html', 'body']],

      // Test that whitespace is collapsed.
      // @todo The whitespace should be collapsed.
      [
        "<p>Drupal  Drupal\n\nDrupal<pre>Drupal  Drupal\n\nDrupal</pre>Drupal  Drupal\n\nDrupal</p>",
        "Drupal  Drupal\n\nDrupalDrupal  Drupal\n\nDrupalDrupal  Drupal\n\nDrupal\n\n",
        ['p'],
      ],

      // Test that headers are properly separated from surrounding text.
      // @todo There should be more space above the headers than below them.
      ['Drupal<h1>Drupal</h1>Drupal', "Drupal\n======== Drupal ==============================================================\n\nDrupal\n"],
      ['<p>Drupal</p><h1>Drupal</h1>Drupal', "Drupal\n\n======== Drupal ==============================================================\n\nDrupal\n"],
      ['Drupal<h1>Drupal</h1><p>Drupal</p>', "Drupal\n======== Drupal ==============================================================\n\nDrupal\n\n"],
      ['<p>Drupal</p><h1>Drupal</h1><p>Drupal</p>', "Drupal\n\n======== Drupal ==============================================================\n\nDrupal\n\n"],

      // Test that footnote references are properly generated.
      // @todo Footnote URLs should be absolute.
      // @todo References [3] and [4] should be combined.
      [<<<EOT
<a href="http://example.com/node/1">Host and path</a>
<br /><a href="http://example.com">Host, no path</a>
<br /><a href="/node/1">Path, no host</a>
<br /><a href="node/1">Relative path</a>
EOT, <<<EOT
Host and path [1]
Host, no path [2]
Path, no host [3]
Relative path [4]

[1] http://example.com/node/1
[2] http://example.com
[3] http://example.com/node/1
[4] node/1

EOT
      ],

      // Test that combinations of paragraph breaks, line breaks, linefeeds,
      // and spaces are properly handled.
      // @todo Trailing line breaks should be trimmed.
      [
        "<p>line 1<br />\nline 2<br />line 3\n<br />line 4</p><p>paragraph</p>",
        "line 1\nline 2\nline 3\nline 4\n\nparagraph\n\n",
      ],
      [
        "<p>line 1<br /> line 2</p> <p>line 4<br /> line 5</p> <p>0</p>",
        "line 1\nline 2\n\nline 4\nline 5\n\n0\n\n",
      ],
    ];
  }

  /**
   * Tests text separated by block-level tags.
   *
   * Test that text separated by block-level tags in HTML get separated by
   * (at least) a newline in the plaintext version.
   *
   * @covers ::htmlToText
   */
  public function testDrupalHtmlToTextBlockTagToNewline(): void {
    $input = <<<'EOT'
[text]
<blockquote>[blockquote]</blockquote>
<br />[br]
<dl><dt>[dl-dt]</dt>
<dt>[dt]</dt>
<dd>[dd]</dd>
<dd>[dd-dl]</dd></dl>
<h1>[h1]</h1>
<h2>[h2]</h2>
<h3>[h3]</h3>
<h4>[h4]</h4>
<h5>[h5]</h5>
<h6>[h6]</h6>
<hr />[hr]
<ol><li>[ol-li]</li>
<li>[li]</li>
<li>[li-ol]</li></ol>
<p>[p]</p>
<ul><li>[ul-li]</li>
<li>[li-ul]</li></ul>
[text]
EOT;
    $input = str_replace(["\r", "\n"], '', $input);
    $output = MailFormatHelper::htmlToText($input);
    $this->assertDoesNotMatchRegularExpression('/\][^\n]*\[/s', $output, 'Block-level HTML tags should force newlines');

    $output_upper = mb_strtoupper($output);
    $upper_input = mb_strtoupper($input);
    $upper_output = MailFormatHelper::htmlToText($upper_input);
    $this->assertEquals($output_upper, $upper_output, 'Tag recognition should be case-insensitive');
  }

  /**
   * Tests \Drupal\Core\Mail\MailFormatHelper::htmlToText() wrapping.
   *
   * RFC 3676 says, "The Text/Plain media type is the lowest common
   * denominator of Internet email, with lines of no more than 998 characters."
   *
   * RFC 2046 says, "SMTP [RFC-821] allows a maximum of 998 octets before the
   * next CRLF sequence."
   *
   * RFC 821 says, "The maximum total length of a text line including the
   * <CRLF> is 1000 characters."
   *
   * @covers ::htmlToText
   */
  public function testVeryLongLineWrap(): void {
    $input = 'Drupal<br /><p>' . str_repeat('x', 2100) . '</p><br />Drupal';
    $output = MailFormatHelper::htmlToText($input);
    $eol = Settings::get('mail_line_endings', PHP_EOL);

    $maximum_line_length = 0;
    foreach (explode($eol, $output) as $line) {
      // We must use strlen() rather than mb_strlen() in order to count octets
      // rather than characters.
      $maximum_line_length = max($maximum_line_length, strlen($line . $eol));
    }
    $this->assertLessThanOrEqual(1000, $maximum_line_length);
  }

  /**
   * @covers ::wrapMail
   */
  public function testWrapMail(): void {
    $delimiter = "End of header\n";
    $long_file_name = $this->randomMachineName(64) . '.docx';
    $headers_in_body = 'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document; name="' . $long_file_name . "\"\n";
    $headers_in_body .= "Content-Transfer-Encoding: base64\n";
    $headers_in_body .= 'Content-Disposition: attachment; filename="' . $long_file_name . "\"\n";
    $headers_in_body .= 'Content-Description: ' . $this->randomMachineName(64);
    $body = $this->randomMachineName(76) . ' ' . $this->randomMachineName(6);
    $wrapped_text = MailFormatHelper::wrapMail($headers_in_body . $delimiter . $body);
    [$processed_headers, $processed_body] = explode($delimiter, $wrapped_text);

    // Check that the body headers were not wrapped even though some exceeded
    // 77 characters.
    $this->assertEquals($headers_in_body, $processed_headers, 'Headers in the body are not wrapped.');
    // Check that the body text is soft-wrapped according to the
    // "format=flowed; delsp=yes" encoding. When interpreting this encoding,
    // mail readers will delete a space at the end of the line; therefore an
    // extra trailing space should be present in the raw body (see RFC 3676).
    $this->assertEquals(wordwrap($body, 77, "  \n"), $processed_body, 'Body text is soft-wrapped.');

    // Check that lines are wrapped properly when they don't contain line breaks.
    $long_text = 'Some long text that extends beyond the wrapping length by only a few characters.';
    $expected = "Some long text that extends beyond the wrapping length by only a few \ncharacters.";
    $this->assertEquals($expected, MailFormatHelper::wrapMail($long_text, ''), 'Text without line breaks is properly wrapped.');
  }

  /**
   * Tests that trailing whitespace is removed before newlines.
   *
   * @covers ::wrapMail
   */
  public function testRemoveTrailingWhitespace(): void {
    $text = "Hi there! \nHerp Derp";
    $mail_lines = explode("\n", MailFormatHelper::wrapMail($text));
    $this->assertNotEquals(" ", substr($mail_lines[0], -1), 'Trailing whitespace removed.');
  }

  /**
   * Tests that trailing whitespace from Usenet style signatures is not removed.
   *
   * RFC 3676 says, "This is a special case; an (optionally quoted or quoted and
   * stuffed) line consisting of DASH DASH SP is neither fixed nor flowed."
   *
   * @covers ::wrapMail
   */
  public function testUsenetSignature(): void {
    $text = "Hi there!\n-- \nHerp Derp";
    $mail_lines = explode("\n", MailFormatHelper::wrapMail($text));
    $this->assertSame("-- ", $mail_lines[1], 'Trailing whitespace not removed for dash-dash-space signatures.');
    $text = "Hi there!\n--  \nHerp Derp";
    $mail_lines = explode("\n", MailFormatHelper::wrapMail($text));
    $this->assertSame("--", $mail_lines[1], 'Trailing whitespace removed for incorrect dash-dash-space signatures.');
  }

  /**
   * @covers ::htmlToTextPad
   * @dataProvider providerHtmlToTextPad
   */
  public function testHtmlToTextPad($text, $pad, $prefix, $expected):void {
    $helper = new MailFormatHelper();
    $method = new \ReflectionMethod($helper, 'htmlToTextPad');
    $method->setAccessible(TRUE);
    $value = $method->invoke($helper, $text, $pad, $prefix);
    $this->assertEquals($expected, $value);
  }

  public static function providerHtmlToTextPad():array {
    return [
      [
        "Simple text\n",
        " ",
        '',
        "Simple text                                                                   \n",
      ],
      [
        "String\n\nwith several\n\nlinebreaks\n",
        " ",
        '',
        "String\n\nwith several\n\nlinebreaks                                                                    \n",
      ],
      [
        "String to pad with a prefix\n",
        '-',
        '::',
        "String to pad with a prefix::-------------------------------------------------\n",
      ],
    ];
  }

  /**
   * @covers ::htmlToMailUrls
   * @dataProvider providerHtmlToMailUrls
   */
  public function testHtmlToMailUrls($match, $label, $url): void {
    $helper = new MailFormatHelper();
    $method = new \ReflectionMethod($helper, 'htmlToMailUrls');
    $method->setAccessible(TRUE);
    $method->invoke($helper, NULL, $this->basePath, $this->baseUrl);

    $this->assertEquals($label, $method->invoke($helper, $match));
    $this->assertEquals([$url], $method->invoke($helper));
  }

  public static function providerHtmlToMailUrls(): array {
    return [
      [
        [
          '<a href="http://example.com">A link is matched!</a>',
          '<a href="http://example.com">A link is matched!</a>',
          'http://example.com',
          'A link is matched!',
        ],
        'A link is matched! [1]',
        'http://example.com',
      ],
      [
        [
          '<a href="/foo/bar">A relative link is matched!</a>',
          '<a href="/foo/bar">A relative link is matched!</a>',
          '/foo/bar',
          'A relative link is matched!',
        ],
        'A relative link is matched! [1]',
        'http://example.com/foo/bar',
      ],
    ];
  }

}
