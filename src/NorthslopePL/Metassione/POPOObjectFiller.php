<?php
namespace NorthslopePL\Metassione;

use NorthslopePL\Metassione\ClassStructure\ClassStructure;
use NorthslopePL\Metassione\ClassStructure\ClassStructureBuilder;
use NorthslopePL\Metassione\ClassStructure\ClassStructureException;
use NorthslopePL\Metassione\ClassStructure\PropertyStructure;
use NorthslopePL\Metassione\Metadata\MetadataHelper;

class POPOObjectFiller
{
	/**
	 * @var ReflectionCache
	 */
	private $reflectionCache;

	public function __construct()
	{
		$this->reflectionCache = new ReflectionCache();
	}

	/**
	 * @param object $targetObject
	 * @param \stdClass $rawData
	 *
	 * @throws ObjectFillingException
	 */
	public function fillObjectWithRawData($targetObject, $rawData)
	{
		$this->processObject($targetObject, $rawData);
	}

	/**
	 * @param object $targetObject
	 * @param \stdClass $rawData
	 *
	 * @throws ObjectFillingException
	 */
	private function processObject($targetObject, $rawData)
	{
		if (!is_object($rawData))
		{
			$message = sprintf('Raw data should be an object, but %s was given. I was trying to fill object of class %s.', gettype($rawData), get_class($targetObject));
			if (is_array($rawData))
			{
				$message .= sprintf(' Maybe you have forgotten about adding "array|" for property that holds this class %s?', get_class($targetObject));
				throw new ObjectFillingException($message);
			}
			else
			{
				throw new ObjectFillingException($message);
			}
		}

		// TODO This cannot be optimized, or can be changed to processing as array with key/values?
		$rawDataReflection = new \ReflectionObject($rawData);

		// FIXME dodać cache na tym poziomie
		$classStructure = $this->reflectionCache->getClassStructureForClassname(get_class($targetObject));

		foreach ($rawDataReflection->getProperties() as $rawDataProperty)
		{
			$this->processProperty($rawDataProperty, $rawData, $targetObject, $classStructure);
		}

	}

	/**
	 * @param \ReflectionProperty $rawDataProperty
	 * @param \stdClass $rawDataObject
	 * @param object $targetObject
	 * @param ClassStructure $classStructure
	 *
	 * @throws ClassStructureException
	 * @throws ObjectFillingException
	 */
	private function processProperty(\ReflectionProperty $rawDataProperty, $rawDataObject, $targetObject, ClassStructure $classStructure)
	{
		$propertyName = $rawDataProperty->getName();
		if (!$classStructure->hasProperty($propertyName))
		{
			return;
		}

		$targetPropertyStructure = $classStructure->getPropertyStructure($propertyName);
		try
		{
			// FIXME do przepisania
			$rawValue = $rawDataProperty->getValue($rawDataObject);
		}
		catch (\ReflectionException $e)
		{
			throw new ObjectFillingException($e);
		}

		$newValue = $this->buildNewPropertyValue($rawValue, $targetPropertyStructure);

		// TODO jeszcze wez ReflectionProperty z klasy lub parentow
		// FIXME jak cacheować ReflectionProperty zeby go ciagle nie pobierac?

		$metadataHelper = new MetadataHelper();
		$targetReflectionProperty = $metadataHelper->getPropertyReflectionFromReflectionClassOrParentClasses(new \ReflectionClass($classStructure->getClassname()), $propertyName); // FIXME
		$targetReflectionProperty->setAccessible(true);
		$targetReflectionProperty->setValue($targetObject, $newValue);
	}


	/**
	 * @param mixed $rawValue
	 * @param PropertyStructure $targetPropertyStructure
	 *
	 * @return mixed
	 *
	 * @throws ObjectFillingException
	 */
	private function buildNewPropertyValue($rawValue, PropertyStructure $targetPropertyStructure)
	{
		if ($targetPropertyStructure->getIsArray())
		{
			if ($targetPropertyStructure->getIsObject())
			{
				return $this->buildNewPropertyValue_ForArrayOfObjects($rawValue, $targetPropertyStructure);
			}
			else
			{
				return $this->buildNewPropertyValue_ForArrayOfPrimitives($rawValue, $targetPropertyStructure);
			}
		}
		else
		{
			// not array

			if ($targetPropertyStructure->getIsObject())
			{
				// single object

				return $this->buildNewPropertyValue_ForObject($rawValue, $targetPropertyStructure);
			}
			else
			{
				// other value
				return $rawValue;
			}
		}
	}

	/**
	 * @param mixed $rawValue
	 * @param PropertyStructure $targetPropertyStructure
	 * @return array|object[]
	 *
	 * @throws ObjectFillingException
	 */
	private function buildNewPropertyValue_ForArrayOfObjects($rawValue, PropertyStructure $targetPropertyStructure)
	{
		// array of objects
		$newValues = [];

		// optimization, take class reflection creation out of the loop. Its 2x faster 1.3s vs 3.3s for 1 000 000 iterations.
		$classname = $targetPropertyStructure->getType();

		$rawValue = is_array($rawValue) ? $rawValue : (array)$rawValue;

		foreach ($rawValue as $rawValueItem) // cast to array handles null values when expecting arrays
		{
			$newValueElement = $this->buildNewInstanceOfClass($classname);
			$this->processObject($newValueElement, $rawValueItem);
			$newValues[] = $newValueElement;
		}

		return $newValues;
	}

	/**
	 * @param mixed $rawValue
	 * @param PropertyStructure $targetPropertyStructure
	 * @return array|mixed[]
	 *
	 * @throws ObjectFillingException
	 */
	private function buildNewPropertyValue_ForArrayOfPrimitives($rawValue, PropertyStructure $targetPropertyStructure)
	{
		// array of primitive types
		$newValues = [];
		// this is object but we dont know the class or this is a simple type
		// @var array|int[]
		// @var array|AClassThatIsNotFound[]
		$rawValue = is_array($rawValue) ? $rawValue : (array)$rawValue;
		foreach ($rawValue as $rawValueItem)
		{
			$newValues[] = $rawValueItem;
		}

		return $newValues;
	}

	/**
	 * @param mixed $rawValue
	 * @param PropertyStructure $targetPropertyStructure
	 * @return object|null
	 *
	 * @throws ObjectFillingException
	 */
	private function buildNewPropertyValue_ForObject($rawValue, PropertyStructure $targetPropertyStructure)
	{
		if ($rawValue === null)
		{
			return null;
		}
		else
		{
			// this property is an object
			//
			// /**
			//  * @var Foobar
			//  */
			// private $fooBar;
			//
			// so build object of this class and fill this object with data from $propertyValue
			$propertyClassname = $targetPropertyStructure->getType();
			$newValue = $this->buildNewInstanceOfClass($propertyClassname);
			$this->processObject($newValue, $rawValue);

			return $newValue;
		}
	}

	// FIXME cache!!! albo RC albo instancji
	private function buildNewInstanceOfClass($classname)
	{
		$classname = ltrim($classname, '\\');

		if (!class_exists($classname, true))
		{
//			$message = sprintf('Class "%s" does not exist for property %s::$%s.', $classname, $targetObjectProperty->getDeclaringClass()->getName(), $targetObjectProperty->getName());
//			$namespaceNotDetectedInClassname = (strpos($classname, '\\') === false);
//			if ($namespaceNotDetectedInClassname)
//			{
//				$message .= sprintf(' Maybe you have forgotten to use fully qualified class name (with namespace, example: \Foo\Bar\%s)?', $classname);
//			}

			throw new ObjectFillingException(sprintf("Class not found: %s", $classname));
		}

		return new $classname(); // FIXME cache + reflection?
	}

}
