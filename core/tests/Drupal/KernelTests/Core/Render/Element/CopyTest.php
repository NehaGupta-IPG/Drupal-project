<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Render\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Generic;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers copying and cloning of element objects and arrays generated from them.
 */
#[Group('Render')]
class CopyTest extends KernelTestBase {

  /**
   * Tests and demonstrates shallow and deep copies of element objects' arrays.
   */
  public function testCopyRenderArray(): void {
    /** @var \Drupal\Core\Render\ElementInfoManagerInterface $elementInfoManager */
    $elementInfoManager = \Drupal::service('plugin.manager.element_info');
    // Create an element object that will wrap a child element with markup.
    $originalElem = $elementInfoManager->fromClass(Generic::class);
    $originalChild = $originalElem->createChild('child', Generic::class);
    $originalMarkup = 'This is the original markup';
    $originalChild->markup = $originalMarkup;

    // Using the assignment operator on a render array built from an element
    // object creates a "shallow" copy where all the nested array and object
    // references will stay intact.
    $originalArray = $originalElem->toRenderable();
    // The ##object property on the base of the array and the child will still
    // reference the original and child elements.
    $shallowCopyArray = $originalArray;
    $this->assertEquals($originalElem, $shallowCopyArray['##object']);
    $this->assertEquals($originalChild, $shallowCopyArray['child']['##object']);

    // A deep copy of the same original render array will unset all nested
    // array and object references.
    $deepCopyArray = Element::copyRenderArrayDeep($originalArray);
    $this->assertArrayNotHasKey('##object', $deepCopyArray);
    $this->assertArrayNotHasKey('##object', $deepCopyArray['child']);

    // When you render the original array, the #printed property is set, which
    // sets the output on another render call to empty. Since the shallow copy
    // references the same array as the original, rendering the original first
    // means that rendering the shallow copy later will result in an empty
    // string. A deep copy will not have its #printed properties set, so it
    // renders the markup as set.
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $this->assertEquals($originalMarkup, $renderer->renderRoot($originalArray));
    $this->assertEmpty($renderer->renderRoot($shallowCopyArray));
    $this->assertEquals($originalMarkup, $renderer->renderRoot($deepCopyArray));

    // Setting a property value on the original child element will set the
    // property on the original array and shallow copy, but not the deep copy.
    $originalTestProperty = 'original test property';
    $originalChild->test_property = $originalTestProperty;
    $this->assertEquals($originalTestProperty, $originalArray['child']['#test_property']);
    $this->assertEquals($originalTestProperty, $shallowCopyArray['child']['#test_property']);
    $this->assertArrayNotHasKey('#test_property', $deepCopyArray['child']);
  }

  /**
   * Test that cloning an element creates a deep copy with references unset.
   */
  public function testCloneElementObject(): void {
    /** @var \Drupal\Core\Render\ElementInfoManagerInterface $elementInfoManager */
    $elementInfoManager = \Drupal::service('plugin.manager.element_info');
    // Create an element object that will wrap a child element with markup, then
    // clone the outer element.
    $originalElem = $elementInfoManager->fromClass(Generic::class);
    $originalChild = $originalElem->createChild('child', Generic::class);
    $originalMarkup = 'This is the original markup';
    $originalChild->markup = $originalMarkup;
    $cloneElem = clone $originalElem;

    // Save references to the render arrays associated with the element and
    // clone element so that updating the element objects will be reflected in
    // their respective arrays that will be rendered later.
    $originalArray = &$originalElem->toRenderable();
    $cloneArray = &$cloneElem->toRenderable();
    // Confirm that the base ##object property of the clone array references the
    // clone element.
    $this->assertEquals($cloneElem, $cloneArray['##object']);
    // Confirm that the ##object property of the child has been unset.
    $this->assertArrayNotHasKey('##object', $cloneArray['child']);

    // Confirm that setting a property value on the original child element does
    // not set the same property on the child of the clone element.
    $cloneChild = $cloneElem->getChild('child');
    $originalTestProperty = 'original test property';
    $originalChild->test_property = $originalTestProperty;
    $this->assertEquals($originalTestProperty, $originalArray['child']['#test_property']);
    $this->assertNull($cloneChild->test_property);
    $this->assertArrayNotHasKey('test_property', $cloneArray['child']);

    // Confirm that changing property on the clone child does not change the
    // same property on the original child.
    $cloneChild->markup = 'This is the clone markup';
    $this->assertNotEquals($cloneChild->markup, $originalChild->markup);
    $originalChild->markup = 'This is revised original markup';

    // Confirm that rendering the original and clone arrays outputs the last
    // set markup values for each respective element.
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $this->assertEquals('This is revised original markup', $renderer->renderRoot($originalArray));
    $this->assertEquals('This is the clone markup', $renderer->renderRoot($cloneArray));
  }

}
