<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File as FileEntity;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\media\MediaInterface;
use Drupal\media_test_oembed\Controller\ResourceController;
use Drupal\Tests\media\Traits\OEmbedTestTrait;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore Schipulcon

/**
 * Tests refreshing media metadata from the user interface.
 */
#[Group('media')]
#[RunTestsInSeparateProcesses]
class MediaUpdateMetadataControllerTest extends MediaFunctionalTestBase {

  use OEmbedTestTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['media_test_oembed'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Creates a file media item and its source file.
   */
  protected function createFileMedia(string $name = 'File media', string $filename = 'foo.txt'): MediaInterface {
    $media_type = MediaType::load('file') ?? $this->createMediaType('file', ['id' => 'file']);
    file_put_contents('public://' . $filename, 'foo');
    $file = FileEntity::create([
      'uri' => 'public://' . $filename,
      'uid' => 1,
    ]);
    $file->setPermanent();
    $file->save();

    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => $name,
      'uid' => 1,
      'field_media_file' => [
        'target_id' => $file->id(),
      ],
    ]);
    $media->save();
    return $media;
  }

  /**
   * Creates a remote video whose title is mapped to field_title.
   */
  protected function createRemoteVideo(): MediaInterface {
    $this->lockHttpClientToFixtures();
    $this->useFixtureProviders();
    $this->hijackProviderEndpoints();
    ResourceController::setResourceUrl('https://vimeo.com/7073899', $this->getFixturesDirectory() . '/video_vimeo.json');

    $media_type = $this->createMediaType('oembed:video');
    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_title',
      'type' => 'string',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $media_type->id(),
    ])->save();
    $media_type->setFieldMap(['title' => 'field_title'])->save();

    $source_field = $media_type->getSource()->getSourceFieldDefinition($media_type)->getName();
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Remote video',
      $source_field => 'https://vimeo.com/7073899',
    ]);
    $media->save();
    $this->assertSame('Drupal Rap Video - Schipulcon09', $media->get('field_title')->value);
    return $media;
  }

  /**
   * Follows a media item's Refresh operation link from the media overview.
   *
   * The link carries a CSRF token for the browser's session, which the test
   * process cannot generate itself.
   */
  protected function clickRefresh(MediaInterface $media): void {
    $this->drupalGet('/admin/content/media');
    $href = base_path() . 'media/' . $media->id() . '/update-metadata';
    $this->assertSession()->elementExists('xpath', "//a[starts-with(@href, '$href?')]")->click();
  }

  /**
   * Reloads a media item from storage.
   */
  protected function reloadMedia(MediaInterface $media): MediaInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('media');
    $storage->resetCache([$media->id()]);
    return $storage->load($media->id());
  }

  /**
   * Tests refreshing from the media overview's operation links.
   */
  public function testRefreshFromOperations(): void {
    $media = $this->createFileMedia();

    // Rename the file, simulating a source metadata change.
    $file = $media->get('field_media_file')->entity;
    $file->setFilename('bar.txt');
    $file->save();

    $this->clickRefresh($media);

    $this->assertSession()->addressEquals('/admin/content/media');
    $this->assertSession()->statusMessageContains('Updated metadata on media item File media.', 'status');
    $this->assertSame('bar.txt', $this->reloadMedia($media)->getSource()->getMetadata($media, 'name'));
  }

  /**
   * Tests that the refresh route requires a CSRF token and update access.
   */
  public function testRefreshAccess(): void {
    $media = $this->createFileMedia();
    $changed = $this->reloadMedia($media)->getChangedTime();

    // Without the token, as from a link on another site, nothing happens.
    $this->drupalGet('/media/' . $media->id() . '/update-metadata');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('/media/' . $media->id() . '/update-metadata', ['query' => ['token' => 'invalid']]);
    $this->assertSession()->statusCodeEquals(403);

    // A user who may view but not update media cannot refresh it.
    $this->drupalLogin($this->drupalCreateUser(['view media', 'access media overview']));
    $this->drupalGet('/admin/content/media');
    $this->assertSession()->linkNotExists('Refresh');
    $this->drupalGet('/media/' . $media->id() . '/update-metadata/confirm');
    $this->assertSession()->statusCodeEquals(403);

    $this->assertSame($changed, $this->reloadMedia($media)->getChangedTime());
  }

  /**
   * Tests refreshing a media item whose source file is missing.
   */
  public function testRefreshMissingSourceFile(): void {
    $media_type = $this->createMediaType('image');
    $source_field = $media_type->getSource()->getSourceFieldDefinition($media_type)->getName();
    $uri = $this->container->get('file_system')->copy($this->getTestFiles('image')[0]->uri, 'public://statue.png');
    $file = FileEntity::create(['uri' => $uri]);
    $file->save();
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Statue',
      $source_field => ['target_id' => $file->id(), 'alt' => 'Statue'],
    ]);
    $media->save();
    $this->assertSame($uri, $media->thumbnail->entity->getFileUri());
    unlink($uri);

    $this->clickRefresh($media);
    $assert_session = $this->assertSession();
    $assert_session->addressEquals('/media/' . $media->id() . '/update-metadata/confirm');
    $assert_session->pageTextContains('The source file for media item Statue is missing, unable to refresh.');
    $assert_session->pageTextNotContains('That is why the media library shows a placeholder icon.');
    $assert_session->buttonExists('Remove thumbnail');

    $this->submitForm([], 'Cancel');
    $assert_session->addressEquals('/admin/content/media');
    $assert_session->statusMessageContains('Canceled. Media item Statue remains unchanged.', 'status');
    $this->assertSame($uri, $this->reloadMedia($media)->thumbnail->entity->getFileUri());

    $this->clickRefresh($media);
    $this->submitForm([], 'Remove thumbnail');
    $assert_session->statusMessageContains('Thumbnail removed for media item Statue.', 'status');
    $media = $this->reloadMedia($media);
    $this->assertStringStartsWith($this->config('media.settings')->get('icon_base_uri') . '/', $media->thumbnail->entity->getFileUri());
    // The stored name survives, although the source no longer provides it.
    $this->assertSame('Statue', $media->label());

    // Refreshing again explains there is nothing left to remove, instead of
    // offering to remove the thumbnail a second time.
    $this->clickRefresh($media);
    $assert_session->pageTextContains('The source file for media item Statue is missing, unable to refresh. That is why the media library shows a placeholder icon.');
    $assert_session->buttonNotExists('Remove thumbnail');
    $this->submitForm([], 'Cancel');
    $assert_session->statusMessageContains('Canceled. Media item Statue remains unchanged.', 'status');
    $assert_session->pageTextNotContains('Thumbnail removed');
  }

  /**
   * Tests refreshing a remote media item whose source is unavailable.
   */
  public function testRefreshUnavailableRemoteSource(): void {
    $media = $this->createRemoteVideo();
    $url = 'https://vimeo.com/7073899';
    ResourceController::setResource404($url);

    $this->clickRefresh($media);
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('The remote source for media item Remote video is unavailable.');
    // Only the form's own explanation is shown, not the source plugin's error.
    $assert_session->pageTextNotContains('Could not retrieve the oEmbed resource.');

    // Retrying while the source is still unavailable shows the form again.
    $this->submitForm([], 'Retry');
    $assert_session->addressEquals('/media/' . $media->id() . '/update-metadata/confirm');
    $assert_session->pageTextContains('The remote source for media item Remote video is unavailable.');
    $assert_session->pageTextNotContains('Could not retrieve the oEmbed resource.');
    $this->assertSame('Drupal Rap Video - Schipulcon09', $this->reloadMedia($media)->get('field_title')->value);

    $this->submitForm([], 'Cancel');
    $assert_session->statusMessageContains('Canceled. Media item Remote video remains unchanged.', 'status');

    // Once the source returns, with changed data, Retry refreshes from it
    // rather than from the copy cached when the item was created.
    ResourceController::setResourceUrl($url, $this->getFixturesDirectory() . '/video_vimeo-no-title.json');
    $this->drupalGet('/media/' . $media->id() . '/update-metadata/confirm');
    $this->submitForm([], 'Refresh');
    $assert_session->statusMessageContains('Updated metadata on media item Remote video.', 'status');
    $this->assertTrue($this->reloadMedia($media)->get('field_title')->isEmpty());
  }

  /**
   * Tests that a refresh fetches the current oEmbed resource.
   */
  public function testRefreshBypassesCachedResource(): void {
    $media = $this->createRemoteVideo();
    ResourceController::setResourceUrl('https://vimeo.com/7073899', $this->getFixturesDirectory() . '/video_vimeo-no-title.json');

    $this->clickRefresh($media);
    $this->assertSession()->statusMessageContains('Updated metadata on media item Remote video.', 'status');
    $this->assertTrue($this->reloadMedia($media)->get('field_title')->isEmpty());
  }

  /**
   * Tests that bulk refresh skips items whose source is missing.
   */
  public function testBulkRefreshSkipsMissingSources(): void {
    $available = $this->createFileMedia();
    $file = $available->get('field_media_file')->entity;
    $file->setFilename('bar.txt');
    $file->save();

    $missing = $this->createFileMedia('Missing media', 'gone.txt');
    unlink('public://gone.txt');

    /** @var \Drupal\system\ActionConfigEntityInterface $action */
    $action = $this->container->get('entity_type.manager')->getStorage('action')->load('media_update_metadata');
    $this->assertSame('Refresh from source', $action->label());
    // The action is offered in the media overview's bulk operations.
    $this->drupalGet('/admin/content/media');
    $this->assertSession()->optionExists('action', 'media_update_metadata');
    $this->assertSession()->pageTextContains('Refresh from source');

    $action->execute([$this->reloadMedia($available), $this->reloadMedia($missing)]);
    $this->assertSame('bar.txt', $this->reloadMedia($available)->getSource()->getMetadata($available, 'name'));

    $messages = $this->container->get('messenger')->messagesByType('warning');
    $this->assertCount(1, $messages);
    $this->assertSame('1 of the 2 selected media items was not refreshed because its source is missing or unavailable: <em class="placeholder">Missing media</em>.', (string) $messages[0]);
    $this->assertSame('Missing media', $this->reloadMedia($missing)->label());
  }

}
