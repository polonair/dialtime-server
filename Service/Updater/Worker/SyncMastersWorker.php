<?php

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Worker;

use Polonairs\Dialtime\ModelBundle\Entity\Gate;

class SyncMastersWorker
{
    private $job = null;
    private $doctrine  = null;

    public function __construct(ServerJob $job, Doctrine $doctrine)
    {
        $this->job = $job;
        $this->doctrine = $doctrine;
    }
    public function doJob()
    {
        $em = $this->doctrine->getManager();
        $gates = $em->getRepository("ModelBundle:Gate")->findAll();
        foreach($gates as $gate)
        {
            $this->syncMastersOn($em, $gate);
        }
    }
    private function syncMastersOn($em, Gate $gate)
    {
    	$connection = $this->createConnection($gate);
    	if ($connection->connect_error === null)
        {
        	$theirs = $this->fetchTheirs($connection);
        	$ours = $this->fetchOurs($em);
        	$remove = [];
        	$insert = [];
        	foreach ($theirs as $t) if (!in_array($t, $ours)) $remove[] = $t;
        	foreach ($ours as $o) if (!in_array($o, $theirs)) $insert[] = $o;
        	$queries = $this->makeSyncQueries($remove, $insert);
            $connection->begin_transaction();
        	foreach($queries as $query) $connection->query($query);
        	$connection->commit();
        	$connection->close();
        }
    }
    private function createConnection(Gate $gate)
    {
        return new \mysqli(
            $gate->getHost(), 
            $gate->getDbUser(),
            $gate->getDbPassword(),
            $gate->getDbName(),
            $gate->getDbPort());    	
    }
    private function fetchTheirs($connection)
    {
    	$result = [];
        $query = "SELECT `masters`.`number` as `m_number` FROM `masters` WHERE 1;";
        if ($result = $connection->query($query))
        {
            $row = $result->fetch_array();
            while($row !== null)
            {
                $result[] = $row['m_number'];
                $row = $result->fetch_array();
            }
        }   
        return $result;
    }
    private function fetchOurs($em) { return $em->getRepository("ModelBundle:Master")->loadAllMasterPhones(); }
    private function makeSyncQueries($remove, $insert)
    {
    	$result = [];
    	foreach ($remove as $r) $result[] = "DELETE FROM `masters` WHERE `masters`.`number` = '$r'; ";
    	foreach ($insert as $i) $result[] = "INSERT INTO `masters` (`number`) VALUES ('$i'); ";
    	return $result;
    }
}
