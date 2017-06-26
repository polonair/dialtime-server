<?php

namespace Polonairs\Dialtime\ServerBundle\Service\Updater;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\SaveWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\FakeSaveWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\BillWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\EventProcessor;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\UpdateSpreadsWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\CloseTasksWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\OpenTasksWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\SendListWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\SyncTasksWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\SyncRoutesWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\SyncDonglesWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\ArchiveRecordsWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\ClearDbWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\UnholdTransactionsWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\AccountCheckWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\SyncMastersWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\SyncForbidsWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Worker\UpdateDonglesWorker;

class Updater
{
	private $doctrine;
	private $sms_sender;

	public function __construct($doctrine, $sms_sender)
	{
		$this->doctrine = $doctrine;
		$this->sms_sender = $sms_sender;
	}
	public function updateAll($serverId, $count = 1)
	{
		$em = $this->doctrine->getManager();
		$jobs = $em->getRepository("ModelBundle:ServerJob")->loadJobsForServerId($serverId);
		//dump($jobs);
		for ($i = 0; $i < $count; $i++) foreach($jobs as $job) $this->doJob($job);
		return "done";
	}
	private function doJob(ServerJob $job)
	{
		$worker = null;
		switch($job->getName())
		{
			case "save": $worker = new SaveWorker($job, $this->doctrine); break;
			case "bill_out": $worker = new BillWorker($job, $this->doctrine); break;
			case "event_process": $worker = new EventProcessor($job, $this->doctrine); break;
			case "update_spreads": $worker = new UpdateSpreadsWorker($job, $this->doctrine); break;
			case "close_tasks": $worker = new CloseTasksWorker($job, $this->doctrine); break;
			case "open_tasks": $worker = new OpenTasksWorker($job, $this->doctrine); break;
			case "clear_db": $worker = new ClearDbWorker($job, $this->doctrine); break;
			case "send_list": $worker = new SendListWorker($job, $this->doctrine, $this->sms_sender); break;
			case "sync_tasks": $worker = new SyncTasksWorker($job, $this->doctrine); break;
			case "sync_masters": $worker = new SyncMastersWorker($job, $this->doctrine); break;
			case "sync_forbids": $worker = new SyncForbidsWorker($job, $this->doctrine); break;
			case "sync_routes": $worker = new SyncRoutesWorker($job, $this->doctrine); break;
			case "sync_dongles": $worker = new SyncDonglesWorker($job, $this->doctrine); break;
			case "update_dongles": $worker = new UpdateDonglesWorker($job, $this->doctrine); break;
			case "check_accounts": $worker = new AccountCheckWorker($job, $this->doctrine); break;
			case "archive_records": $worker = new ArchiveRecordsWorker($job, $this->doctrine); break;
			case "unhold_transactions": $worker = new UnholdTransactionsWorker($job, $this->doctrine); break;
			default: break;
		}
		if ($worker !== null) $worker->doJob();
	}
}
