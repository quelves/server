<?php
/**
 * @package Scheduler
 * @subpackage Bulk-Upload
 */
require_once ("bootstrap.php");

setlocale ( LC_ALL, 'en_US.UTF-8' );

/**
 * Will initiate a single bulk upload.
 * The state machine of the job is as follows:
 * get the csv, parse it and validate it
 * creates the entries
 *
 * @package Scheduler
 * @subpackage Bulk-Upload
 */
class KAsyncBulkUpload extends KBatchBase {
	
	/**
	 * @return number
	 */
	public static function getType() {
		return KalturaBatchJobType::BULKUPLOAD;
	}
	
	protected function init() {
		$this->saveQueueFilter ( self::getType () );
	}
	
	public function run() {
		KalturaLog::info ( "Bulk upload batch is running" );
		
		if ($this->taskConfig->isInitOnly ())
			return $this->init ();
		
		$jobs = $this->kClient->batch->getExclusiveBulkUploadJobs ( $this->getExclusiveLockKey (), $this->taskConfig->maximumExecutionTime, 1, $this->getFilter () );
		
		KalturaLog::info ( count ( $jobs ) . " bulk upload jobs to perform" );
		
		if (! count ( $jobs )) {
			KalturaLog::info ( "Queue size: 0 sent to scheduler" );
			$this->saveSchedulerQueue ( self::getType () );
			return false;
		}
		
		$jobResults = array();
		ini_set('auto_detect_line_endings', true);
		foreach ( $jobs as $job ) 
		{
			try {
				$jobResults[] = $this->startBulkUpload($job);
			}
			catch (KalturaBulkUploadAbortedException $abortedException)
			{
				$jobResults[] = $this->closeJob($job, null, null, null, KalturaBatchJobStatus::ABORTED);
			}
			catch(KalturaException $kex)
			{
				$jobResults[] = $this->closeJob($job, KalturaBatchJobErrorTypes::KALTURA_API, $kex->getCode(), "Error: " . $kex->getMessage(), KalturaBatchJobStatus::FAILED);
			}
			catch(KalturaClientException $kcex)
			{
				$jobResults[] = $this->closeJob($job, KalturaBatchJobErrorTypes::KALTURA_CLIENT, $kcex->getCode(), "Error: " . $kcex->getMessage(), KalturaBatchJobStatus::RETRY);
			}
			catch(Exception $ex)
			{
				$jobResults[] = $this->closeJob($job, KalturaBatchJobErrorTypes::RUNTIME, $ex->getCode(), "Error: " . $ex->getMessage(), KalturaBatchJobStatus::FAILED);
			}
		}
		ini_set('auto_detect_line_endings', false);
		
		return $jobResults;
	}
	
	/**
	 * 
	 * Starts the bulk upload
	 * @param KalturaBatchJob $job
	 * @param KalturaBulkUploadJobData $bulkUploadJobData
	 */
	private function startBulkUpload(KalturaBatchJob $job)
	{
		KalturaLog::debug ( "startBulkUpload($job->id)" );
		
		//Gets the right Engine instance 
		$engine = KBulkUploadEngine::getEngine($job->jobSubType, $this->taskConfig, $this->kClient, $job);
		if (is_null ( $engine )) {
			throw new KalturaException ( "Unable to find bulk upload engine", KalturaBatchJobAppErrors::ENGINE_NOT_FOUND );
		}

		$engine->handleBulkUpload();
		$job = $engine->getJob();
		$data = $engine->getData();

		return $this->closeJob($job, null, null, 'Waiting for imports and conversion', KalturaBatchJobStatus::ALMOST_DONE, $data);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see KBatchBase::updateExclusiveJob()
	 */
	protected function updateExclusiveJob($jobId, KalturaBatchJob $job) {
		return $this->kClient->batch->updateExclusiveBulkUploadJob ( $jobId, $this->getExclusiveLockKey (), $job );
	}
	
	/**
	 * (non-PHPdoc)
	 * @see KBatchBase::freeExclusiveJob()
	 */
	protected function freeExclusiveJob(KalturaBatchJob $job) {
		$resetExecutionAttempts = false;
		if ($job->status == KalturaBatchJobStatus::ALMOST_DONE)
			$resetExecutionAttempts = true;
		
		$response = $this->kClient->batch->freeExclusiveBulkUploadJob ( $job->id, $this->getExclusiveLockKey (), $resetExecutionAttempts );
		
		KalturaLog::info ( "Queue size: $response->queueSize sent to scheduler" );
		$this->saveSchedulerQueue ( self::getType (), $response->queueSize );
		
		return $response->job;
	}
}
