<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\SaveWorker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;
use Polonairs\Dialtime\ModelBundle\Entity\Route;
use Polonairs\Dialtime\ModelBundle\Entity\Call;
use Polonairs\Dialtime\ModelBundle\Entity\Gate;

class SaveWorker implements WorkerInterface 
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
        $gates = $em->getRepository("ModelBundle:Gate")->findAll();
        foreach($gates as $gate)
        {
            $this->saveFrom($gate);
        }
    }
    private function saveFrom(Gate $gate)
    {
        $em = $this->doctrine->getManager();
        $connection = new \mysqli(
            $gate->getHost(), 
            $gate->getDbUser(),
            $gate->getDbPassword(),
            $gate->getDbName(),
            $gate->getDbPort());

        if ($connection->connect_error === null)
        {
            $compositions = [];
            $connection->begin_transaction();
            $query = "
                SELECT 
                    `calls`.`id` as `call_id`,
                    `calls`.`route_id` as `call_route_id`,
                    `calls`.`direction` as `call_direction`,
                    `calls`.`result` as `call_result`,
                    `calls`.`dial_length` as `call_dial_length`,
                    `calls`.`answer_length` as `call_answer_length`,
                    `calls`.`record` as `call_record`,
                    `calls`.`created_at` as `call_created_at`,
                    `routes`.`id` as `route_id`,
                    `routes`.`sid` as `route_sid`,
                    `routes`.`task_id` as `route_task_id`,
                    `routes`.`state` as `route_state`,
                    `routes`.`customer` as `route_customer`,
                    `routes`.`originator` as `route_originator`,
                    `routes`.`master` as `route_master`,
                    `routes`.`terminator` as `route_terminator`,
                    `routes`.`expired_at` as `route_expired_at`,
                    `routes`.`created_at` as `route_created_at`
                FROM `calls` 
                INNER JOIN `routes` on `calls`.`route_id` = `routes`.`id` 
                WHERE 1;";
            if ($result = $connection->query($query))
            {
                $row = $result->fetch_array();
                while($row !== null)
                {
                    $compositions[] = $row;
                    $row = $result->fetch_array();
                }
            }         
            $routes = [];
            $calls = [];
            foreach($compositions as $composition)
            {
                if (!array_key_exists($composition['route_id'], $routes))
                {
                    if ($composition['route_sid'] === null)
                    {
                        $dr = $em->getRepository("ModelBundle:Dongle");
                        $pr = $em->getRepository("ModelBundle:Phone");
                        $tr = $em->getRepository("ModelBundle:Task");
                        $routes[$composition['route_id']] = (new Route())
                            ->setCustomerNumber($composition['route_customer'])
                            ->setMasterPhone($pr->loadByNumber($composition['route_master']))
                            ->setOriginator($dr->loadByNumber($composition['route_originator']))
                            ->setTerminator($dr->loadByNumber($composition['route_terminator']))
                            ->setTask(($composition['route_task_id'] === null)?(null):($tr->find($composition['route_task_id'])))
                            ->setState($composition['route_state'])
                            ->setExpiredAt(new \DateTime($composition['route_expired_at']));
                    }
                    else
                    {
                        $rr = $em->getRepository("ModelBundle:Route");
                        $routes[$composition['route_id']] = $rr->findById($composition['route_sid'])[0];
                    }
                }
                $calls[$composition['call_id']] = (new Call())
                    ->setRoute($routes[$composition['call_route_id']])
                    ->setCreatedAt(new \DateTime($composition['call_created_at']))
                    ->setDirection($composition['call_direction'])
                    ->setDialLength($composition['call_dial_length'])
                    ->setAnswerLength($composition['call_answer_length'])
                    ->setResult($composition['call_result'])
                    ->setRecord($composition['call_record'])
                    ->setTransaction(null); // !!!
            }
            foreach ($calls as $call) $em->persist($call);
            foreach ($routes as $route) $em->persist($route);

            $em->flush();

            $q = "";
            foreach ($routes as $key => $route)
            { 
                $q .= sprintf("UPDATE `routes` SET `sid` = %d WHERE `id` = %d; ", $route->getId(), $key);
            }
            echo $q;
            $connection->multi_query($q);
            $connection->multi_query("DELETE FROM `calls` WHERE 1;");
            $connection->commit();

            $connection->close();
        }
    }
}
