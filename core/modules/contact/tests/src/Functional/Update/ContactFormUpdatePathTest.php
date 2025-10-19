<?php

declare(strict_types=1);

namespace Drupal\Tests\contact\Functional\Update;

use Drupal\contact\Entity\ContactForm;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests update path for the contact form values from '' to NULL.
 *
 * @group contact
 * @covers contact_post_update_set_empty_values_to_null
 */
class ContactFormUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../../../system/tests/fixtures/update/remove-message-from-contact-feedback-form.php',
    ];
  }

  /**
   * Tests update path for the contact form's values from '' to NULL.
   */
  public function testRunUpdates(): void {
    $form = ContactForm::load('feedback');
    $this->assertSame('', $form->get('message'));
    $this->assertSame('', $form->get('redirect'));
    $this->assertSame('', $form->get('reply'));
    $this->assertSame('', $form->getMessage());
    $this->assertSame('', $form->getRedirectPath());
    $this->assertSame('', $form->getReply());

    $this->runUpdates();

    $form = ContactForm::load('feedback');
    $this->assertNull($form->get('message'));
    $this->assertNull($form->get('redirect'));
    $this->assertNull($form->get('reply'));
    $this->assertNull($form->getMessage());
    $this->assertNull($form->getRedirectPath());
    $this->assertNull($form->getReply());
  }

}
