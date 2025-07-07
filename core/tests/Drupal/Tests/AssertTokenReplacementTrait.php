<?php

declare(strict_types=1);

namespace Drupal\Tests\Traits\Core;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Provides test assertions for testing token replacements.
 */
trait AssertTokenReplacementTrait {

  /**
   * Checks token replacement and metadata.
   *
   * Asserts if tokens are correctly replaced and verifies that the correct
   * metadata has been set.
   *
   * @param array $tests
   *   Expected results keyed by their respective tokens.
   * @param array $data
   *   The data to perform the replacement on. @see Token::replace().
   * @param array $options
   *   Additional replacement options. @see Token::replace().
   * @param string $message
   *   The message to display with the assertion. Can contain replacement
   *   tokens:
   *   - %token for the token name
   *   - %output for the value returned by the token replacement service
   *   - %expected for the expected result.
   * @param array $metadata_tests
   *   The metadata to verify. Keyed by the relevant token.
   */
  protected function assertTokenReplacementAndCheckMetadata(array $tests, array $data, array $options, string $message, array $metadata_tests): void {
    foreach ($tests as $token => $expected) {
      $bubbleable_metadata = new BubbleableMetadata();
      $output = \Drupal::token()->replace($token, $data, $options, $bubbleable_metadata);
      $this->assertEquals($output, new HtmlEscapedText($expected), new FormattableMarkup($message, [
        '%token' => $token,
        '%output' => $output,
        '%expected' => $expected,
      ])->__toString());

      $this->assertEquals($metadata_tests[$token], $bubbleable_metadata);
    }
  }

  /**
   * Asserts if tokens are correctly replaced.
   *
   * @param array $tests
   *   Expected results keyed by their respective tokens.
   * @param array $data
   *   The data to perform the replacement on. @see Token::replace().
   * @param array $options
   *   Additional replacement options. @see Token::replace().
   * @param string $message
   *   The message to display with the assertion. Can contain replacement
   *   tokens:
   *   - %token for the token name
   *   - %output for the value returned by the token replacement service
   *   - %expected for the expected result.
   */
  protected function assertTokenReplacement(array $tests, array $data, array $options, string $message): void {
    foreach ($tests as $token => $expected) {
      $output = \Drupal::token()->replace($token, $data, $options);
      $expected = ($expected instanceof MarkupInterface) ? $expected : new HtmlEscapedText($expected);
      $this->assertEquals($output, $expected, new FormattableMarkup($message, [
        '%token' => $token,
        '%output' => $output,
        '%expected' => $expected,
      ])->__toString());
    }
  }

}
