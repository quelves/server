<?php

/**
 * @package plugins.schedule_task
 * @subpackage Admin
 */

class ScheduleTaskUtils
{
	public static function getSchemeMap($object)
	{
		if (!$object)
			return array();
		try
		{
			$className = get_class($object);
			$classObj = new $className();
			if ($classObj instanceof Kaltura_Client_Reach_Type_EntryVendorTask)
				return array("id", "entryId", "userId", "status", "createdAt", "queueTime");
			if ($classObj instanceof Kaltura_Client_Type_BaseEntry)
				return array("id", "name", "userId", "views", "createdAt", "lastPlayedAt");
			return array();
		}
		catch (Exception $e)
		{
			KalturaLog::err($e->getMessage());
			return array();
		}
	}
}