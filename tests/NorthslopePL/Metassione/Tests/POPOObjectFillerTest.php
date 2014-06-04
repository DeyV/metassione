<?php
namespace NorthslopePL\Metassione\Tests;

use NorthslopePL\Metassione\POPOObjectFiller;
use NorthslopePL\Metassione\Tests\Blog\Blog;

class POPOObjectFillerTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var POPOObjectFiller
	 */
	private $objectFiller;

	protected function setUp()
	{
		$this->objectFiller = new POPOObjectFiller();
	}

	public function testFillingEmptyKlass()
	{
		$targetObject = new EmptyKlass();
		$rawData = new \stdClass();

		$this->objectFiller->fillObjectWithRawData($targetObject, $rawData);

		$expected = new EmptyKlass();
		$this->assertEquals($expected, $targetObject);
	}

	public function testFillingEmptyKlassWithUnknownProperties()
	{
		$targetObject = new EmptyKlass();
		$rawData = new \stdClass();
		$rawData->foo = 'bar';

		$this->objectFiller->fillObjectWithRawData($targetObject, $rawData);

		$expected = new EmptyKlass();
		$this->assertEquals($expected, $targetObject, '"foo" property should not be added');
	}

	public function testFillingSimpleKlassWithEmptyData()
	{
		$simpleObject = new SimpleKlass();
		$rawData = new \stdClass();
		$this->objectFiller->fillObjectWithRawData($simpleObject, $rawData);

		$expectedObject = new SimpleKlass();
		$this->assertEquals($expectedObject, $simpleObject, 'all properties have default values');
	}

	public function testFillingSimpleKlassWithAllItsProperties()
	{
		$simpleObject = new SimpleKlass();

		$rawData = new \stdClass();
		$rawData->nullValue = null;
		$rawData->boolValue = true;
		$rawData->intValue = 42;
		$rawData->floatValue = 12.95;
		$rawData->stringValue = 'foobar';

		$this->objectFiller->fillObjectWithRawData($simpleObject, $rawData);

		$expectedObject = new SimpleKlass();
		$expectedObject->setNullValue(null);
		$expectedObject->setBoolValue(true);
		$expectedObject->setIntValue(42);
		$expectedObject->setFloatValue(12.95);
		$expectedObject->setStringValue('foobar');

		$this->assertEquals($expectedObject, $simpleObject);
	}

	public function testComplexObjectHierarchyIsFilledWithComplexStdClassesAndArrays()
	{
		$blogBuilder = new TestBlogBuilder();
		$rawBlogData = $blogBuilder->buildBlogAsStdClass();

		$blog = new Blog();
		$this->objectFiller->fillObjectWithRawData($blog, $rawBlogData);

		$expectedBlog = $blogBuilder->buildBlog();

		$this->assertEquals(print_r($expectedBlog, true), print_r($blog, true));
		$this->assertEquals($expectedBlog, $blog);
	}

	public function testFillingSimpleObjectWithRawDataThatIsNotAnObject()
	{
		$simpleObject = new SimpleKlass();
		$rawData = 'foobar';

		$this->setExpectedException('NorthslopePL\Metassione\ObjectFillingException', 'Raw data should be an object, but string was given. I was trying to fill object of class NorthslopePL\Metassione\Tests\SimpleKlass.');

		$this->objectFiller->fillObjectWithRawData($simpleObject, $rawData);
	}

	public function testFillingObjectWhenItsPropertysClassDoesNotExist()
	{
		$targetObject = new PropertyNotFoundKlass();
		$rawData = new \stdClass();
		$rawData->foo = 'bar';

		$this->setExpectedException('NorthslopePL\Metassione\ObjectFillingException', 'Class "NorthslopePL\Metassione\Tests\Foo" does not exist for property NorthslopePL\Metassione\Tests\PropertyNotFoundKlass::$foo');
		$this->objectFiller->fillObjectWithRawData($targetObject, $rawData);
	}

	public function testFillingArrays()
	{
		$targetObject = new ArrayedKlass();
		$rawData = new \stdClass();

		$object1 = new \stdClass();
		$object1->value = 1;
		$object2 = new \stdClass();
		$object2->value = 2;
		$rawData->objects = array($object1, $object2);
		$rawData->objects2 = array($object1, $object2);
		$rawData->objects3 = array($object1, $object2);
		$rawData->numbers = array(4, 5, 6);


		$this->objectFiller->fillObjectWithRawData($targetObject, $rawData);

		$object1 = new OnePropertyKlass();
		$object1->setValue(1);
		$object2 = new OnePropertyKlass();
		$object2->setValue(2);

		$expected = new ArrayedKlass();
		$expected->setObjects(array($object1, $object2));
		$expected->setObjects2(array($object1, $object2));
		$expected->setObjects3(array($object1, $object2));
		$expected->setNumbers(array(4, 5, 6));

		$this->assertEquals(print_r($expected, true), print_r($targetObject, true));
		$this->assertEquals($expected, $targetObject);
	}

	public function testSettingPropertyThatIsAnObject()
	{
		$targetObject = new OneObjectPropertyKlass();
		$rawData = new \stdClass();
		$rawData->value = (object)array('value' => 42);

		$this->objectFiller->fillObjectWithRawData($targetObject, $rawData);

		$expectedObject = new OneObjectPropertyKlass();
		$expectedSubObject = new OnePropertyKlass();
		$expectedSubObject->setValue(42);
		$expectedObject->setValue($expectedSubObject);

		$this->assertEquals($expectedObject, $targetObject);
	}

	public function testFillingObjectWithIncorrectlyDocumentedArrayProperty()
	{
		$targetObject = new ArrayedKlass();

		$rawData = new \stdClass();
		{
			$object1 = new \stdClass();
			$object1->value = 1;
			$object2 = new \stdClass();
			$object2->value = 2;
		}
		$rawData->invalidSpecificationForObjects = array($object1, $object2);

		$this->setExpectedException('NorthslopePL\Metassione\ObjectFillingException', 'Raw data should be an object, but array was given. I was trying to fill object of class NorthslopePL\Metassione\Tests\OnePropertyKlass. Maybe you have forgotten about adding "array|" for property that holds this class NorthslopePL\Metassione\Tests\OnePropertyKlass');
		$this->objectFiller->fillObjectWithRawData($targetObject, $rawData);
	}

	public function testFillingObjectUsingDataWithPrivatePropertiesThrowsException()
	{
		$targetObject = new OnePropertyKlass();
		$sourceData = new OnePropertyKlass();
		$sourceData->setValue(42);

		$this->setExpectedException('NorthslopePL\Metassione\ObjectFillingException', 'exception \'ReflectionException\' with message '); // ...
		$this->objectFiller->fillObjectWithRawData($targetObject, $sourceData);
	}

	public function testUsingUnqalifiedClassname()
	{
		$targetObject = new PropertyNotFoundKlass();

		$sourceData = new \stdClass();
		$sourceData->one = (object)array('value' => 42);

		$this->setExpectedException('NorthslopePL\Metassione\ObjectFillingException', 'Class "OnePropertyKlass" does not exist for property NorthslopePL\Metassione\Tests\PropertyNotFoundKlass::$one. Maybe you have forgotten to use fully qualified class name (with namespace, example: \Foo\Bar\OnePropertyKlass)?');
		$this->objectFiller->fillObjectWithRawData($targetObject, $sourceData);
	}
}