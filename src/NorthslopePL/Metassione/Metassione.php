<?php
namespace NorthslopePL\Metassione;

use NorthslopePL\Metassione\ClassStructure\ClassStructureProvider;
use NorthslopePL\Metassione\ClassStructure\SimpleClassStructureProvider;

class Metassione
{
	/**
	 * @param object|array $object POJO
	 * @return \stdClass
	 */
	public function convertToStdClass($object)
	{
		$converter = new POPOConverter();
		return $converter->convert($object);
	}

	/**
	 * @param object $targetObject POJO
	 * @param \stdClass $rawData
	 *
	 * @throws ObjectFillingException
	 */
	public function fillObjectWithRawData($targetObject, \stdClass $rawData)
	{
		$filler = new POPOObjectFiller();
		$filler->fillObjectWithRawData($targetObject, $rawData);
	}
}
