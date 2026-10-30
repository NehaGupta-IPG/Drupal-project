<?php

declare(strict_types=1);

namespace Drupal\Tests\contact\Kernel;

use Drupal\contact\ContactMessageStorage;
use Drupal\contact\Entity\ContactForm;
use Drupal\contact\Entity\Message;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Tests ContactMessageStorage sends mail and enforces flood control.
 */
#[Group('contact')]
#[RunTestsInSeparateProcesses]
class ContactMessageStorageTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'contact',
    'contact_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['contact', 'contact_test']);
    $this->installMailCollector();
  }

  /**
   * Installs the test mail collector as the default mail backend.
   */
  protected function installMailCollector(): void {
    $this->container->get('config.factory')->getEditable('system.mail')
      ->set('interface.default', 'test_mail_collector')
      ->save();
  }

  /**
   * Configures contact flood control settings for testing.
   */
  protected function getFloodConfig(): void {
    $this->config('contact.settings')
      ->set('flood.limit', 2)
      ->set('flood.interval', 3600)
      ->save();
  }

  /**
   * Tests that the contact_message storage uses ContactMessageStorage.
   */
  public function testStorageClassIsCorrect(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('contact_message');
    $this->assertInstanceOf(ContactMessageStorage::class, $storage);
  }

  /**
   * Tests that saving a contact message sends an email to recipients.
   */
  public function testSaveSendsEmail(): void {
    ContactForm::create([
      'id' => 'test_form',
      'label' => 'Test form',
      'recipients' => ['recipient@example.com'],
      'reply' => '',
      'message' => 'Your message has been sent.',
    ])->save();

    $message = Message::create([
      'contact_form' => 'test_form',
      'subject' => 'Test subject',
      'message' => 'Test body',
      'name' => 'Sender',
      'mail' => 'sender@example.com',
    ]);
    $message->save();

    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $this->assertNotEmpty($mails, 'An email was sent after saving a contact message.');
    $this->assertEquals('contact', $mails[0]['module']);
    $this->assertEquals('page_mail', $mails[0]['key']);
    $this->assertEquals('recipient@example.com', $mails[0]['to']);
  }

  /**
   * Tests that saving a contact message registers a flood event.
   */
  public function testSaveRegistersFloodEvent(): void {
    $this->getFloodConfig();
    $flood = $this->container->get('flood');

    ContactForm::create([
      'id' => 'flood_test',
      'label' => 'Flood test',
      'recipients' => ['recipient@example.com'],
    ])->save();

    $message = Message::create([
      'contact_form' => 'flood_test',
      'subject' => 'Test',
      'message' => 'Test',
      'name' => 'Sender',
      'mail' => 'sender@example.com',
    ]);
    $message->save();

    $this->assertFalse($flood->isAllowed('contact', 2, 3600));
  }

  /**
   * Tests that flood control blocks saving after the limit is reached.
   */
  public function testFloodControlBlocksSave(): void {
    $this->getFloodConfig();

    ContactForm::create([
      'id' => 'flood_block_test',
      'label' => 'Flood block test',
      'recipients' => ['recipient@example.com'],
    ])->save();

    for ($i = 0; $i < 2; $i++) {
      $message = Message::create([
        'contact_form' => 'flood_block_test',
        'subject' => "Test $i",
        'message' => "Test $i",
        'name' => 'Sender',
        'mail' => 'sender@example.com',
      ]);
      $message->save();
    }

    $this->expectException(TooManyRequestsHttpException::class);
    $message = Message::create([
      'contact_form' => 'flood_block_test',
      'subject' => 'Blocked',
      'message' => 'Blocked',
      'name' => 'Sender',
      'mail' => 'sender@example.com',
    ]);
    $message->save();
  }

  /**
   * Tests that flood control is bypassed for users with administer permission.
   */
  public function testFloodControlBypassedForAdmin(): void {
    $this->getFloodConfig();
    $admin = $this->createUser(['administer contact forms']);
    $this->container->get('current_user')->setAccount($admin);

    ContactForm::create([
      'id' => 'admin_flood_test',
      'label' => 'Admin flood test',
      'recipients' => ['recipient@example.com'],
    ])->save();

    for ($i = 0; $i < 5; $i++) {
      $message = Message::create([
        'contact_form' => 'admin_flood_test',
        'subject' => "Test $i",
        'message' => "Test $i",
        'name' => 'Admin',
        'mail' => 'admin@example.com',
      ]);
      $message->save();
    }

    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $this->assertCount(5, $mails, 'All emails sent for admin user bypassing flood.');
  }

  /**
   * Tests that an autoreply email is sent when the contact form has a reply.
   */
  public function testAutoreplySentForFormWithReply(): void {
    ContactForm::create([
      'id' => 'autoreply_test',
      'label' => 'Autoreply test',
      'recipients' => ['recipient@example.com'],
      'reply' => 'Thank you for contacting us!',
    ])->save();

    $message = Message::create([
      'contact_form' => 'autoreply_test',
      'subject' => 'Test',
      'message' => 'Test',
      'name' => 'Sender',
      'mail' => 'sender@example.com',
    ]);
    $message->save();

    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $keys = array_map(fn($m) => $m['key'], $mails);
    $this->assertContains('page_autoreply', $keys, 'Autoreply email was sent.');
  }

}
