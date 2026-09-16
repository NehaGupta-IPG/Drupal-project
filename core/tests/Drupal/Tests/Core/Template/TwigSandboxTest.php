<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Template;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Template\Attribute\TwigAllowed;
use Drupal\Core\Template\Loader\StringLoader;
use Drupal\Core\Template\TwigSandboxPolicy;
use Drupal\Tests\Core\Entity\ContentEntityBaseMockableClass;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Sandbox\SecurityError;

/**
 * Tests the twig sandbox policy.
 */
#[CoversClass(TwigSandboxPolicy::class)]
#[Group('Template')]
class TwigSandboxTest extends UnitTestCase {

  /**
   * The Twig environment loaded with the sandbox extension.
   *
   * @var \Twig\Environment
   */
  protected $twig;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $loader = new StringLoader();
    $this->twig = new Environment($loader);
    $policy = new TwigSandboxPolicy();
    $sandbox = new SandboxExtension($policy, TRUE);
    $this->twig->addExtension($sandbox);
  }

  /**
   * Tests that dangerous methods cannot be called in entity objects.
   */
  #[DataProvider('getTwigEntityDangerousMethods')]
  public function testEntityDangerousMethods(string $template): void {
    $this->expectException(SecurityError::class);
    $this->twig->render($template, ['entity' => $this->createStub(EntityInterface::class)]);
  }

  /**
   * Data provider for ::testEntityDangerousMethods.
   *
   * @return array
   *   An array of dangerous methods.
   */
  public static function getTwigEntityDangerousMethods(): array {
    return [
      ['{{ entity.delete }}'],
      ['{{ entity.save }}'],
      ['{{ entity.create }}'],
    ];
  }

  /**
   * Tests that allowed classes can be extended.
   */
  public function testExtendedClass(): void {
    $this->assertEquals(' class=&quot;kitten&quot;', $this->twig->render('{{ attribute.addClass("kitten") }}', ['attribute' => new TestAttribute()]));
  }

  /**
   * Tests that prefixed methods can be called from within Twig templates.
   *
   * Currently "get", "has", and "is" are the only allowed prefixes.
   */
  public function testEntitySafePrefixes(): void {
    $entity = $this->createMock('Drupal\Core\Entity\EntityInterface');
    $entity->expects($this->atLeastOnce())
      ->method('hasLinkTemplate')
      ->with('test')
      ->willReturn(TRUE);
    $result = $this->twig->render('{{ entity.hasLinkTemplate("test") }}', ['entity' => $entity]);
    $this->assertTrue((bool) $result, 'Sandbox policy allows has* functions to be called.');

    $entity = $this->createMock('Drupal\Core\Entity\EntityInterface');
    $entity->expects($this->atLeastOnce())
      ->method('isNew')
      ->willReturn(TRUE);
    $result = $this->twig->render('{{ entity.isNew }}', ['entity' => $entity]);
    $this->assertTrue((bool) $result, 'Sandbox policy allows is* functions to be called.');

    $entity = $this->createMock('Drupal\Core\Entity\EntityInterface');
    $entity->expects($this->atLeastOnce())
      ->method('getEntityType')
      ->willReturn('test');
    $result = $this->twig->render('{{ entity.getEntityType }}', ['entity' => $entity]);
    $this->assertEquals('test', $result, 'Sandbox policy allows get* functions to be called.');
  }

  /**
   * Tests that valid methods can be called from within Twig templates.
   *
   * Currently the following methods are in the allowed list: get.
   *
   * Other methods allowed by the TwigAllowed attribute: bundle, id, label,
   * toLink, and toUrl.
   */
  public function testEntitySafeMethods(): void {
    $entity = $this->getMockBuilder(ContentEntityBaseMockableClass::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entity->expects($this->atLeastOnce())
      ->method('get')
      ->with('title')
      ->willReturn('test title get');
    $result = $this->twig->render('{{ entity.get("title") }}', ['entity' => $entity]);
    $this->assertSame('test title get', $result, 'Sandbox policy allows get() to be called.');

    $entity->expects($this->atLeastOnce())
      ->method('id')
      ->willReturn('1234');
    $result = $this->twig->render('{{ entity.id }}', ['entity' => $entity]);
    $this->assertSame('1234', $result, 'Sandbox policy allows id() to be called.');

    $result = $this->twig->render('{{ entity.id }}', ['entity' => new AnonymousUserSession()]);
    $this->assertSame('0', $result, 'Sandbox policy allows id() to be called.');

    $entity->expects($this->atLeastOnce())
      ->method('label')
      ->willReturn('test label');
    $result = $this->twig->render('{{ entity.label }}', ['entity' => $entity]);
    $this->assertSame('test label', $result, 'Sandbox policy allows label() to be called.');

    $entity->expects($this->atLeastOnce())
      ->method('bundle')
      ->willReturn('test bundle');
    $result = $this->twig->render('{{ entity.bundle }}', ['entity' => $entity]);
    $this->assertSame('test bundle', $result, 'Sandbox policy allows bundle() to be called.');

    $entity->expects($this->atLeastOnce())
      ->method('toLink')
      ->willReturn('<a href="/node/123">Entity Title</a>');
    $result = $this->twig->render('{{ entity.toLink() }}', ['entity' => $entity]);
    $this->assertSame(Html::escape('<a href="/node/123">Entity Title</a>'), $result, 'Sandbox policy allows toLink() to be called.');

    $entity->expects($this->atLeastOnce())
      ->method('toUrl')
      ->willReturn('/node/123/edit');
    $result = $this->twig->render('{{ entity.toUrl("edit") }}', ['entity' => $entity]);
    $this->assertSame('/node/123/edit', $result, 'Sandbox policy allows toUrl() to be called.');
  }

  /**
   * Tests that safe methods inside Url objects can be called.
   */
  public function testUrlSafeMethods(): void {
    // Only mock toString(), because the Url class's own setMethod() method
    // needs to be reflected for the TwigAllowed attribute.
    $url = $this->getMockBuilder('Drupal\Core\Url')
      ->disableOriginalConstructor()
      ->onlyMethods(['toString'])
      ->getMock();
    $setAbsolute = FALSE;
    $url->expects($this->exactly(2))
      ->method('toString')
      ->willReturnCallback(static function () use (&$setAbsolute): string {
        return $setAbsolute ? 'http://kittens.cat/are/cute' : '/are/cute';
      });
    $result = $this->twig->render('{{ url.toString }}', ['url' => $url]);
    $this->assertSame('/are/cute', $result, 'Sandbox policy allows toString() to be called.');

    $setAbsolute = TRUE;
    $result = $this->twig->render('{{ url.setAbsolute.toString }}', ['url' => $url]);
    $this->assertSame('http://kittens.cat/are/cute', $result, 'Sandbox policy allows setAbsolute() to be called.');
  }

  /**
   * Tests that method with TwigAllowed attribute is allowed.
   */
  public function testTwigMethodAttributeAllowed(): void {
    $object = new AttributeAllowTestClass();
    $result = $this->twig->render('{{ object.allowed() }}', ['object' => $object]);
    $this->assertTrue((bool) $result, 'TwigAllowed attribute allows method to be called.');
    // Attribute is on the interface method and should be allowed.
    $result = $this->twig->render('{{ object.interfaceAllowed() }}', ['object' => $object]);
    $this->assertTrue((bool) $result, 'TwigAllowed attribute allows interface method to be called.');
  }

  /**
   * Tests that method without TwigAllowed attribute is not allowed.
   */
  public function testTwigMethodAttributeNotAllowed(): void {
    $object = new AttributeAllowTestClass();
    $throwable = NULL;
    try {
      // No attribute is on the method, and the method is not defined in an
      // interface.
      $this->twig->render('{{ object.notAllowed() }}', ['object' => $object]);
    }
    catch (\Throwable $throwable) {
    }
    $this->assertInstanceOf(SecurityError::class, $throwable);

    $throwable = NULL;
    try {
      // No attribute is on the method. The method extends a method in a parent
      // class, and the parent class has the attribute.
      $this->twig->render('{{ object.baseAllowedChildNotAllowed() }}', ['object' => $object]);
    }
    catch (\Throwable $throwable) {
    }
    $this->assertInstanceOf(SecurityError::class, $throwable);

    $throwable = NULL;
    try {
      // No attribute is on the method. The method is defined in an interface,
      // but the interface method does not have the attribute.
      $this->twig->render('{{ object.interfaceNotAllowed() }}', ['object' => $object]);
    }
    catch (\Throwable $throwable) {
    }
    $this->assertInstanceOf(SecurityError::class, $throwable);

  }

}

/**
 * Test class for HTML attributes collector, sanitizer, and renderer.
 */
class TestAttribute extends Attribute {}

/**
 * Test interface for TwigAllowed attribute.
 */
interface AttributeAllowTestInterface {

  #[TwigAllowed]
  public function interfaceAllowed(): string;

  public function interfaceNotAllowed(): string;

}

/**
 * Test base class for TwigAllowed attribute.
 */
abstract class AttributeAllowTestClassBase implements AttributeAllowTestInterface {

  #[TwigAllowed]
  public function baseAllowedChildNotAllowed(): string {
    return __METHOD__;
  }

  public function interfaceNotAllowed(): string {
    return __METHOD__;
  }

}

/**
 * Test class for TwigAllowed attribute.
 */
class AttributeAllowTestClass extends AttributeAllowTestClassBase {

  #[TwigAllowed]
  public function allowed(): string {
    return __METHOD__;
  }

  public function notAllowed(): string {
    return __METHOD__;
  }

  public function interfaceAllowed(): string {
    return __METHOD__;
  }

  public function baseAllowedChildNotAllowed(): string {
    return __METHOD__;
  }

}
