<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\SyncDonglesWorker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class SyncDonglesWorker
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
		$_dongles = $em->getRepository("ModelBundle:Dongle")->loadAll();
		$dongles = [];
		foreach($_dongles as $d)
		{
			if ($d->getGate() !== null)
			{
				$dongles[$d->getGate()->getId()][$d->getId()] = $d;
			}
		}

		foreach ($gates as $gate) 
		{
	        $connection = new \mysqli(
	            $gate->getHost(), 
	            $gate->getDbUser(),
	            $gate->getDbPassword(),
	            $gate->getDbName(),
	            $gate->getDbPort());
	        if ($connection->connect_error === null)
	        {
	        	$gate_dongles = [];
            	$connection->begin_transaction();
	            if ($result = $connection->query("SELECT * FROM `dongles` WHERE 1;"))
	            {
	                $row = $result->fetch_array();
	                while($row !== null)
	                {
	                    $gate_dongles[$row["id"]] = $row;
	                    $row = $result->fetch_array();
	                }
	            }

				$delete = [];
	            foreach ($gate_dongles as $k => $v)
	            {
	            	if (!array_key_exists($k, $dongles[$gate->getId()]))
	            	{
	            		$delete[] = $k;
	            	}
	            }

				$insert = [];
	            foreach ($dongles[$gate->getId()] as $k => $v)
	            {
	            	if (!array_key_exists($k, $gate_dongles))
	            	{
	            		$insert[] = $k;
	            	}
	            }

	            $qd = "";
	            if (count($delete) > 0)
	            {
	            	$s = "";
	            	foreach($delete as $d) $s .= "$d, ";
	            	$s = substr($s, 0, -2);
	            	$qd = "DELETE FROM `dongles` WHERE `id` IN ($s);";
	            }

	            $qi = "";
	            if (count($insert) > 0)
	            {
	            	$s = "";
	            	// Y-m-d H:i:s
	            	foreach($insert as $i)
	            		$s .= sprintf(
	            			"(%d, '%s', '%s', '%s', NOW()), ",
	            			$dongles[$gate->getId()][$i]->getId(),
	            			"ACTIVE",
	            			$dongles[$gate->getId()][$i]->getNumber(),
	            			$dongles[$gate->getId()][$i]->getPassVoice());
	            	$s = substr($s, 0, -2);
	            	$qi = "INSERT INTO `dongles` (`id`, `state`, `number`, `voice_password`, `created_at`) VALUES $s;";
	            }



	            $connection->multi_query("$qd $qi");
				$connection->commit();
				$connection->close();
			}
			// coinnect
			// load all gates'
			// load all ours'
			// compare
			// delete 
			// insert
			// update
		}
        // sync dongles
	}
}
