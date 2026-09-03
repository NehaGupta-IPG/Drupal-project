<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\EventSubscriber;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\SchemaObjectExistsException;
use Drupal\Core\Database\SqlExecutionInfo;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\EventSubscriber\FinalExceptionSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests Drupal\Core\EventSubscriber\FinalExceptionSubscriber.
 */
#[CoversClass(FinalExceptionSubscriber::class)]
#[Group('EventSubscriber')]
class FinalExceptionSubscriberTest extends UnitTestCase {

  /**
   * Tests on exception with unknown format.
   */
  public function testOnExceptionWithUnknownFormat(): void {
    $config_factory = $this->getConfigFactoryStub();

    $kernel = $this->prophesize(HttpKernelInterface::class);
    $request = Request::create('/test');
    // \Drupal\Core\StackMiddleware\NegotiationMiddleware normally takes care
    // of this so we'll hard code it here.
    $request->setRequestFormat('bananas');
    $e = new MethodNotAllowedHttpException(['POST', 'PUT'], 'test message');
    $event = new ExceptionEvent($kernel->reveal(), $request, HttpKernelInterface::MAIN_REQUEST, $e);
    $subscriber = new TestDefaultExceptionSubscriber($config_factory);
    $subscriber->setStringTranslation($this->getStringTranslationStub());
    $subscriber->onException($event);
    $response = $event->getResponse();

    $this->assertInstanceOf(Response::class, $response);
    $this->assertStringStartsWith('The website encountered an unexpected error. Try again later.<br><br><em class="placeholder">Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException</em>: test message in ', $response->getContent());
    $this->assertEquals(405, $response->getStatusCode());
    $this->assertEquals('POST, PUT', $response->headers->get('Allow'));
    // Also check that the text/plain content type was added.
    $this->assertEquals('text/plain', $response->headers->get('Content-Type'));
  }

  /**
   * Tests on exception with a database exception carrying no statement info.
   *
   * Most of the database exceptions thrown by core carry no SqlExecutionInfo,
   * so there is no dump to append. The response then holds the exception
   * message only - escaped, since a driver message can contain user input -
   * and no leftover dump placeholder.
   */
  public function testOnExceptionWithDatabaseExceptionWithoutInfo(): void {
    $config_factory = $this->getConfigFactoryStub();

    $kernel = $this->prophesize(HttpKernelInterface::class);
    $request = Request::create('/test');
    $e = new SchemaObjectExistsException('Table "<script>alert(\'xss\')</script>" already exists.');
    $event = new ExceptionEvent($kernel->reveal(), $request, HttpKernelInterface::MAIN_REQUEST, $e);
    $subscriber = new TestDefaultExceptionSubscriber($config_factory);
    $subscriber->setStringTranslation($this->getStringTranslationStub());
    $subscriber->onException($event);
    $response = $event->getResponse();

    $this->assertInstanceOf(Response::class, $response);
    $this->assertEquals(500, $response->getStatusCode());
    $content = $response->getContent();
    $this->assertStringContainsString('<em class="placeholder">Drupal\Core\Database\SchemaObjectExistsException</em>: Table &quot;&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;&quot; already exists. in ', $content);
    $this->assertStringNotContainsString('<script>', $content);
    // No SqlExecutionInfo means no dump, and no dangling placeholder either.
    $this->assertStringNotContainsString('SQL statement', $content);
    $this->assertStringNotContainsString('@sql_dump', $content);
  }

  /**
   * Tests on exception with a database exception wrapped by another one.
   *
   * A query failure raised through the entity API surfaces as an
   * \Drupal\Core\Entity\EntityStorageException, which is not a database
   * exception. The statement of the failing query still has to be reported.
   */
  public function testOnExceptionWithWrappedDatabaseException(): void {
    $config_factory = $this->getConfigFactoryStub();

    $info = new SqlExecutionInfo(
      'INSERT INTO {node_field_data} ([title]) VALUES (:db_insert_placeholder_0)',
      [':db_insert_placeholder_0' => 'Bananas are awesome'],
    );
    $database_exception = new DatabaseExceptionWrapper(
      $info->appendToMessage('SQLSTATE[22001]: String data, right truncated'),
      0,
      NULL,
      $info,
    );
    // Wrapped as \Drupal\Core\Entity\Sql\SqlContentEntityStorage does it.
    $e = new EntityStorageException($database_exception->getMessage(), 0, $database_exception);

    $kernel = $this->prophesize(HttpKernelInterface::class);
    $request = Request::create('/test');
    $event = new ExceptionEvent($kernel->reveal(), $request, HttpKernelInterface::MAIN_REQUEST, $e);
    $subscriber = new TestDefaultExceptionSubscriber($config_factory);
    $subscriber->setStringTranslation($this->getStringTranslationStub());
    $subscriber->onException($event);
    $response = $event->getResponse();

    $this->assertInstanceOf(Response::class, $response);
    $this->assertEquals(500, $response->getStatusCode());
    $content = $response->getContent();
    // The wrapper is the exception being reported.
    $this->assertStringContainsString('<em class="placeholder">Drupal\Core\Entity\EntityStorageException</em>', $content);
    // The statement of the wrapped failure is dumped below it.
    $this->assertMatchesRegularExpression('/SQL statement.*INSERT.*INTO.*node_field_data/s', $content);
    $this->assertMatchesRegularExpression('/Placeholders.*Bananas are awesome/s', $content);
    // The wrapper copied the message, so the compact statement it carries is
    // taken off, since the dump repeats it in full.
    $this->assertStringNotContainsString(':db_insert_placeholder_0=', $content);
  }

}

/**
 * Test class for testing the final exception subscriber.
 */
class TestDefaultExceptionSubscriber extends FinalExceptionSubscriber {

  protected function isErrorDisplayable($error): bool {
    return TRUE;
  }

  protected function simplifyFileInError($error) {
    return $error;
  }

  protected function isErrorLevelVerbose(): bool {
    return TRUE;
  }

}
