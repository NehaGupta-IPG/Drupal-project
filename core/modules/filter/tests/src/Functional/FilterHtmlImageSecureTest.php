<?php

declare(strict_types=1);

namespace Drupal\Tests\filter\Functional;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\filter\Entity\FilterFormat;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests restriction of IMG tags in HTML input.
 */
#[Group('filter')]
#[RunTestsInSeparateProcesses]
class FilterHtmlImageSecureTest extends BrowserTestBase {

  use CommentTestTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'node', 'comment', 'image'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup Filtered HTML text format.
    $filtered_html_format = FilterFormat::create([
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'filters' => [
        'filter_html' => [
          'status' => 1,
          'settings' => [
            'allowed_html' => '<img src test-attribute> <a>',
          ],
        ],
        'filter_autop' => [
          'status' => 1,
        ],
        'filter_html_image_secure' => [
          'status' => 1,
        ],
      ],
    ]);
    $filtered_html_format->save();

    // Setup users.
    $webUser = $this->drupalCreateUser([
      'access content',
      'access comments',
      'post comments',
      'skip comment approval',
      $filtered_html_format->getPermissionName(),
    ]);
    $this->drupalLogin($webUser);

    // Setup a node to comment and test on.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    // Add a comment field.
    $this->addDefaultCommentField('node', 'page');
  }

  /**
   * Tests removal of images having a non-local source.
   */
  public function testImageSource(): void {
    global $base_url;

    $node = $this->drupalCreateNode();

    $public_files_path = PublicStream::basePath();
    // Private files are served through the system.private_file_download route
    // at /system/files/{filepath}, not from the physical private base path.
    $private_files_path = 'system/files';

    $http_base_url = preg_replace('/^https?/', 'http', $base_url);
    $https_base_url = preg_replace('/^https?/', 'https', $base_url);
    $files_path = base_path() . $public_files_path;
    $csrf_path = $public_files_path . '/' . implode('/', array_fill(0, substr_count($public_files_path, '/') + 1, '..'));

    $druplicon = 'core/misc/druplicon.png';
    $red_x_image = base_path() . 'core/misc/icons/e32700/error.svg';

    // Put a test image in the files directory.
    $test_images = $this->getTestFiles('image');
    $test_image = $test_images[0]->filename;

    // Put a test image in the files directory with special filename.
    // cspell:ignore tést fïle nàme
    $special_filename = 'tést fïle nàme.png';
    $special_image = rawurlencode($special_filename);
    $special_uri = str_replace($test_images[0]->filename, $special_filename, $test_images[0]->uri);
    \Drupal::service('file_system')->copy($test_images[0]->uri, $special_uri);

    // Put a test image in the private files directory.
    $private_uri = str_replace('public://', 'private://', $test_images[0]->uri);
    \Drupal::service('file_system')->copy($test_images[0]->uri, $private_uri);

    // Create private images whose filenames contain URL-sensitive characters.
    $private_filenames = [$test_image, 'private+image.png', 'private?#image.png'];
    foreach (array_slice($private_filenames, 1) as $private_filename) {
      \Drupal::service('file_system')->copy($test_images[0]->uri, 'private://' . $private_filename);
    }

    // Create an actual derivative and use core's generated destination token.
    $image_style = ImageStyle::load('thumbnail');
    $derivative_uri = $image_style->buildUri($private_uri);
    $this->assertTrue($image_style->createDerivative($private_uri, $derivative_uri));
    $derivative_url = $image_style->buildUrl($private_uri);
    parse_str(parse_url($derivative_url, PHP_URL_QUERY), $derivative_query);
    $this->assertSame($image_style->getPathToken($private_uri), $derivative_query[ImageStyleInterface::TOKEN]);
    $relative_derivative_url = \Drupal::service('file_url_generator')->transformRelative($derivative_url);

    // Create a list of test image sources.
    // The keys become the value of the IMG 'src' attribute, the values are the
    // expected filter conversions.
    $host = \Drupal::request()->getHost();
    $host_pattern = '|^http\://' . $host . '(\:[0-9]{0,5})|';
    $images = [
      $http_base_url . '/' . $druplicon => base_path() . $druplicon,
      $https_base_url . '/' . $druplicon => base_path() . $druplicon,
      // Test a URL that includes a port.
      preg_replace($host_pattern, 'http://' . $host . ':', $http_base_url . '/' . $druplicon) => base_path() . $druplicon,
      preg_replace($host_pattern, 'http://' . $host . ':80', $http_base_url . '/' . $druplicon) => base_path() . $druplicon,
      preg_replace($host_pattern, 'http://' . $host . ':443', $http_base_url . '/' . $druplicon) => base_path() . $druplicon,
      preg_replace($host_pattern, 'http://' . $host . ':8080', $http_base_url . '/' . $druplicon) => base_path() . $druplicon,
      base_path() . $druplicon => base_path() . $druplicon,
      $files_path . '/' . $test_image => $files_path . '/' . $test_image,
      $http_base_url . '/' . $public_files_path . '/' . $test_image => $files_path . '/' . $test_image,
      $https_base_url . '/' . $public_files_path . '/' . $test_image => $files_path . '/' . $test_image,
      $http_base_url . '/' . $public_files_path . '/' . $special_image => $files_path . '/' . $special_image,
      $https_base_url . '/' . $public_files_path . '/' . $special_image => $files_path . '/' . $special_image,
      $http_base_url . '/' . $private_files_path . '/' . $test_image => base_path() . $private_files_path . '/' . $test_image,
      $https_base_url . '/' . $private_files_path . '/' . $test_image => base_path() . $private_files_path . '/' . $test_image,
      // Test an existing private derivative with a valid destination token.
      $derivative_url => $relative_derivative_url,
      $derivative_url . '#fragment' => $relative_derivative_url . '#fragment',
      $files_path . '/example.png' => $red_x_image,
      // Test a private path referencing a file that does not exist: it must be
      // replaced with the placeholder image.
      $http_base_url . '/' . $private_files_path . '/does-not-exist.png' => $red_x_image,
      'http://example.com/' . $druplicon => $red_x_image,
      'https://example.com/' . $druplicon => $red_x_image,
      'javascript:druplicon.png' => $red_x_image,
      $csrf_path . '/logout' => $red_x_image,
      // Test a url containing a query string.
      $http_base_url . '/' . $druplicon . '?query=string' => base_path() . $druplicon . '?query=string',
    ];
    // Query strings and fragments must remain in rendered URLs, but must not
    // become part of the private filesystem path used to validate the image.
    foreach ($private_filenames as $private_filename) {
      $private_paths = [rawurlencode($private_filename)];
      if (!str_contains($private_filename, '?')) {
        $private_paths[] = $private_filename;
      }
      foreach (array_unique($private_paths) as $private_filename_path) {
        $private_image_url = base_path() . $private_files_path . '/' . $private_filename_path;
        foreach (['', '?query=string', '#fragment', '?query=string#fragment'] as $suffix) {
          $images[$private_image_url . $suffix] = $private_image_url . $suffix;
          $images[$https_base_url . '/' . $private_files_path . '/' . $private_filename_path . $suffix] = $private_image_url . $suffix;
        }
      }
    }
    $images[$http_base_url . '/' . $private_files_path . '/does-not-exist.png?query=string#fragment'] = $red_x_image;
    $images['https://example.com/' . $private_files_path . '/' . $test_image . '?query=string#fragment'] = $red_x_image;

    $comment = [];
    foreach ($images as $image => $converted) {
      // Output the image source as plain text for debugging.
      $comment[] = $image . ':';
      // Hash the image source in a custom test attribute, because it might
      // contain characters that confuse XPath.
      $comment[] = '<img src="' . $image . '" test-attribute="' . hash('sha256', $image) . '" />';
    }
    $edit = [
      'comment_body[0][value]' => implode("\n", $comment),
    ];
    $this->drupalGet('node/' . $node->id());
    $this->submitForm($edit, 'Save');
    foreach ($images as $image => $converted) {
      $found = FALSE;
      foreach ($this->getNodeElementsByXpath('//img[@test-attribute="' . hash('sha256', $image) . '"]') as $element) {
        $found = TRUE;
        if ($converted == $red_x_image) {
          $this->assertEquals($red_x_image, $element->getAttribute('src'));
          $this->assertEquals('Image removed.', $element->getAttribute('alt'));
          $this->assertEquals('This image has been removed. For security reasons, only images from the local domain are allowed.', $element->getAttribute('title'));
          $this->assertEquals('16', $element->getAttribute('height'));
          $this->assertEquals('16', $element->getAttribute('width'));
        }
        else {
          $this->assertEquals($converted, $element->getAttribute('src'), $image);
        }
      }
      $this->assertTrue($found, "$image was found.");
    }
  }

}
