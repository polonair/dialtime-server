<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\SyncTasksWorker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ModelBundle\Entity\Gate;
use Polonairs\Dialtime\ModelBundle\Entity\Phone;
use Polonairs\Dialtime\ModelBundle\Entity\Dongle;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class SyncTasksWorker
{
    private $job = null;
    private $doctrine  = null;

	public function __construct() { }
	public function setJob(ServerJob $job)
	{
        $this->job = $job;
	}
	public function setDoctrine(Doctrine $doctrine)
	{
        $this->doctrine = $doctrine;
	}
	public function doJob()
	{
		$em = $this->doctrine->getManager();
		$gates = $em->getRepository("ModelBundle:Gate")->loadAllIndexed();
		$tasks = $em->getRepository("ModelBundle:Task")->loadActive();
		$dongles = $em->getRepository("ModelBundle:Dongle")->loadAll();
		$routes = $em->getRepository("ModelBundle:Route")->loadActive();
		$dongles_by_campaign = [];
		$dongles_by_gate = [];

		foreach($dongles as $dongle)
		{
			$dongles_by_campaign[$dongle->getCampaign()===null ? 'null' : $dongle->getCampaign()->getId()][] = $dongle;
			$dongles_by_gate[$dongle->getGate()->getId()][] = $dongle;
		}

		$routes_by_master_phone = [];

		foreach($routes as $route)
		{
			$routes_by_master_phone[$route->getMasterPhone()->getId()][] = $route;
		}

		$uploads = [];
		foreach($tasks as $task)
		{
			$originators = $dongles_by_campaign[$task->getCampaign()->getId()];
			foreach($originators as $originator)
			{
				$tid = $task->getId();
				$pid = $task->getOffer()->getPhone()->getId();
				$gid = $originator->getGate()->getId();
				$portion = [
					'sid'             => $tid,
					'state'           => 'ACTIVE',
					'originator'      => $originator->getNumber(),
					'master'          => $task->getOffer()->getPhone()->getNumber(),
					'terminators'     => "",
					'active_interval' => 'P14D'];
				$terminators = $this->getTerminators(
					$originator->getGate(), 
					$task->getOffer()->getPhone(), 
					$originator, 
					$dongles_by_gate[$originator->getGate()->getId()], 
					(array_key_exists($task->getOffer()->getPhone()->getId(), $routes_by_master_phone))?
						($routes_by_master_phone[$task->getOffer()->getPhone()->getId()]):
						([]));
				if (count($terminators) > 0)
				{
					$portion['terminators'] = $terminators;
					$uploads[$tid][$pid][$gid][] = $portion;
				}
			}
		}
		$gids = [];
		foreach($uploads as $tid => $_uploads)
		{
			foreach($_uploads as $pid => $__uploads)
			{
				$max_gid = 0;
				$max_val = 0;
				foreach($__uploads as $gid => $___uploads)
				{
					if (count($___uploads) >= $max_val)
					{
						$max_val = count($___uploads);
						$max_gid = $gid;
					}
				}
				$gids[$tid][$pid] = $max_gid;
			}
		}
		$action = [];
		foreach($uploads as $tid => $_uploads)
		{
			foreach($_uploads as $pid => $__uploads)
			{
				$g = $gids[$tid][$pid];
				$action[$g][$tid][$pid] = $uploads[$tid][$pid][$g];
			}
		}
		foreach($action as $gid => $line)
		{
			$gate = $gates[$gid];
	        $connection = new \mysqli(
	            $gate->getHost(), 
	            $gate->getDbUser(),
	            $gate->getDbPassword(),
	            $gate->getDbName(),
	            $gate->getDbPort());
	        if ($connection->connect_error === null)
	        {
            	$connection->begin_transaction();
            	$connection->multi_query("DELETE FROM `tasks` WHERE `state` = 'ACTIVE';");
            	$q = "
            		INSERT 
            		INTO `tasks`
            		(`sid`, `state`, `originator`, `master`, `terminators`, `active_interval`) 
            		VALUES  ";
				foreach($line as $tid => $_line)
				{
					foreach($_line as $pid => $__line)
					{
						foreach($__line as $___line)
						{
							$q .= sprintf("(%d, '%s', '%s', '%s', '%s', '%s'), ",
								$___line['sid'],
								$___line['state'],
								$___line['originator'],
								$___line['master'],
								json_encode($___line['terminators']),
								$___line['active_interval']);
						}
					}
				}
	            $q = substr($q, 0, -2) . ";";
	            //echo $q;
	            $connection->multi_query($q);
				$connection->commit();
				$connection->close();
			}
		}
	}
	private function getTerminators(Gate $gate, Phone $phone, Dongle $originator, array $all_terminators, array $routes)
	{
		$em = $this->doctrine->getManager();
		$keys = [];
		foreach ($all_terminators as $key => $dongle) 
		{
			if (Dongle::equals($dongle, $originator)) continue;
			$continue = false;
			foreach($routes as $route)
				if ($continue = Dongle::equals($dongle, $route->getTerminator())) 
					break;
			if ($continue) continue;
			$keys[] = $key;
		}
		$date = [];
		$used = [];
		foreach($keys as $key)
		{
			$latest = $em->getRepository("ModelBundle:Call")->loadLatestForTerminator($all_terminators[$key]);
			if ($latest !== null) $date[$key] = $latest->getCreatedAt();
			else $date[$key] = new \DateTime("@0");
			$used[$key] = ($all_terminators[$key]->getCampaign() !== null);
		}

		$nkeys = [];
		foreach($keys as $key)
		{
			$nkeys[] = ["key" => $key, "date" => $date[$key], "used" => $used[$key]];
		}

		usort($nkeys, function($a, $b)
			{
				if ($a['used'] && !$b['used']) return 1;
				else if (!$a['used'] && $b['used']) return -1;
				else
				{
					if ($a['date'] < $b['date']) return 1;
					else if ($a['date'] > $b['date']) return -1;
					return 0;
				}
			});

		$result = [];
		foreach ($nkeys as $key) 
		{
			$result[] = $all_terminators[$key['key']]->getNumber();
		}
		return $result;
	}
}
