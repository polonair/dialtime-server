<?php

namespace Polonairs\Dialtime\ServerBundle\Service\Updater;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\SaveWorker\SaveWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\FakeSaveWorker\FakeSaveWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\BillWorker\BillWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\UpdateSpreadsWorker\UpdateSpreadsWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\CloseTasksWorker\CloseTasksWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\OpenTasksWorker\OpenTasksWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\SendListWorker\SendListWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\SyncTasksWorker\SyncTasksWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\SyncRoutesWorker\SyncRoutesWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\SyncDonglesWorker\SyncDonglesWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\ArchiveRecordsWorker\ArchiveRecordsWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\ClearDbWorker\ClearDbWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\UnholdTransactionsWorker\UnholdTransactionsWorker;
use Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\AccountCheckWorker\AccountCheckWorker;

class Updater
{
	private $doctrine;

	public function __construct($doctrine)
	{
		$this->doctrine = $doctrine;
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
			case "save":                $worker = new SaveWorker();               break; 
			case "bill_out":            $worker = new BillWorker();               break; 
			case "update_spreads":      $worker = new UpdateSpreadsWorker();      break; 
			case "close_tasks":         $worker = new CloseTasksWorker();         break; 
			case "open_tasks":          $worker = new OpenTasksWorker();          break; 
			case "clear_db":            $worker = new ClearDbWorker();            break;
			case "send_list":           $worker = new SendListWorker();           break;
			case "sync_tasks":          $worker = new SyncTasksWorker();          break;
			case "sync_routes":         $worker = new SyncRoutesWorker();         break;
			case "sync_dongles":        $worker = new SyncDonglesWorker();        break;
			case "check_accounts":      $worker = new AccountCheckWorker();       break;
			case "archive_records":     $worker = new ArchiveRecordsWorker();     break;
			case "unhold_transactions": $worker = new UnholdTransactionsWorker(); break;
			default: return;
		}
		if ($worker !== null)
		{
            $worker->setDoctrine($this->doctrine);
			$worker->setJob($job);
			$worker->doJob();
		}
	}
}
