<?php

/**
 * metadataBatch service base test case.
 */
abstract class MetadataBatchServiceBaseTest extends KalturaApiUnitTestCase
{
	/**
	 * Called when all tests are done
	 * @param int $id
	 * @return int
	 */
	abstract public function testFinished($id);

}
