<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Worker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;
use Polonairs\Dialtime\ModelBundle\Entity\Route;
use Polonairs\Dialtime\ModelBundle\Entity\Call;
use Polonairs\Dialtime\ModelBundle\Entity\Gate;

class SaveWorker
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
            $this->saveFrom($em, $gate);
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
    private function fetchCompositions($connection)
    {
        $compositions = [];
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
        return $compositions;
    }
    private function createRoute($em, $comp)
    {
        $dr = $em->getRepository("ModelBundle:Dongle");
        $pr = $em->getRepository("ModelBundle:Phone");
        $tr = $em->getRepository("ModelBundle:Task");
        return (new Route())
            ->setCustomerNumber($comp['route_customer'])
            ->setMasterPhone($pr->loadByNumber($comp['route_master']))
            ->setOriginator($dr->loadByNumber($comp['route_originator']))
            ->setTerminator($dr->loadByNumber($comp['route_terminator']))
            ->setTask(($comp['route_task_id'] === null)?(null):($tr->find($comp['route_task_id'])))
            ->setState($comp['route_state']);
    }
    private function createCall($routes, $composition)
    {
        return (new Call())
            ->setRoute($routes[$composition['call_route_id']])
            ->setCreatedAt(new \DateTime($composition['call_created_at']))
            ->setDirection($composition['call_direction'])
            ->setDialLength($composition['call_dial_length'])
            ->setAnswerLength($composition['call_answer_length'])
            ->setResult($composition['call_result'])
            ->setRecord($composition['call_record'])
            ->setTransaction(null); // !!!
    }
    private function getUpdateSidQuery($routes)
    {
        $result = [];
        foreach ($routes as $key => $route)
            $result[] = sprintf(
                "UPDATE `routes` SET `sid` = %d WHERE `id` = %d; ", 
                $route->getId(), 
                $key);
        return $result;
    }
    private function getRoute($em, $sid, $composition)
    {
        if ($sid === null) return $this->createRoute($em, $composition);
        return $em->getRepository("ModelBundle:Route")->findOneById($sid);
    }
    private function saveFrom($em, Gate $gate)
    {
        $connection = $this->createConnection($gate);
        if ($connection->connect_error === null)
        {
            $routes = [];
            $calls = [];
            $connection->begin_transaction();
            $compositions = $this->fetchCompositions($connection);
            foreach($compositions as $c)
            {
                if (!array_key_exists($c['route_id'], $routes))
                {
                    $routes[$c['route_id']] = $this->getRoute($em, $c['route_sid'], $c);
                    $em->persist($routes[$c['route_id']]);
                }
                $calls[$c['call_id']] = $this->createCall($routes, $c);
                $em->persist($calls[$c['call_id']]);
            }
            $em->flush();

            $qs = $this->getUpdateSidQuery($routes);
            foreach($qs as $q) $connection->query($q);
            $connection->commit();
            $connection->query("DELETE FROM `calls` WHERE 1;");
            $connection->commit();
            $connection->close();
        }
    }
}
