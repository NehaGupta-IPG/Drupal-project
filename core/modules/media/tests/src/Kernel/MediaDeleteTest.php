<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Kernel;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\Hook\MediaHooks;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

#[Group('media')]
#[CoversMethod(MediaHooks::class, 'mediaDelete')]
class MediaDeleteTest extends KernelTestBase {

  use MediaTypeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'image',
    'media',
    'system',
    'user',
  ];

  private readonly LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['media', 'system']);
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('user');
    $this->installSchema('file', ['file_usage']);
    $this->createMediaType('image', ['id' => 'image']);

    $this->logger = new TestLogger();
    $this->container->get('logger.factory')->addLogger($this->logger);
  }

  public function testDeleteOwnFile(): void {
    $owner = $this->setUpCurrentUser(permissions: ['delete own files']);

    $uri = $this->getRandomGenerator()
      ->image(uniqid('public://') . '.png', '100x100', '200x200');
    $this->assertFileExists($uri);
    $file = File::create(['uri' => $uri]);
    $file->setOwner($owner)->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => $this->randomMachineName(),
      'field_media_image' => $file,
    ]);
    $media->save();
    $media->delete();

    $predicate = function (array $record) use ($media): bool {
      return (
        $record['level'] === RfcLogLevel::NOTICE &&
        $record['message'] === 'The file attached to media item %name was deleted.' &&
        $record['context']['%name'] = $media->label()
      );
    };
    $this->assertTrue($this->logger->hasRecordThatPasses($predicate));

    $file = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('file')
      ->loadUnchanged($file->id());
    $this->assertNull($file);
  }

}
