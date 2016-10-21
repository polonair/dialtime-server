<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Worker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;
use Polonairs\Dialtime\ModelBundle\Entity\Route;
use Polonairs\Dialtime\ModelBundle\Entity\Call;

class FakeSaveWorker implements WorkerInterface 
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
        return $this->testDoJob();
    }
    private function testDoJob()
    {
        if ($this->job === null || $this->doctrine === null) return;
        $em = $this->doctrine->getManager();

        $routes = $em->getRepository("ModelBundle:Route")->loadActive();
        $dongles = $em->getRepository("ModelBundle:Dongle")->findAll();
        
        $customer = "";
        if (count($routes) > 0 && rand(0, 10) >= 3) // old customer
            $customer = $routes[rand(0, count($routes)-1)]->getCustomerNumber();
        else // new customer
            $customer = "7950001".rand(1000, 9999);

        $originator = $dongles[rand(0, count($dongles)-1)];
        $originator->getNumber();

        $route = $em->getRepository("ModelBundle:Route")->loadRouteForOrigination($customer, $originator);

        if ($route !== null)
        {
            $al = rand(30, 600);
            $dl = $al + rand(6, 20);
            $call = (new Call())
                ->setId(uniqid("", true))
                ->setRoute($route)
                ->setDirection(rand(0, 10) > 5 ? Call::DIRECTION_MO: Call::DIRECTION_MT )
                ->setDialLength($dl)
                ->setAnswerLength($al)
                ->setResult(Call::RESULT_ANSWER)
                ->setRecord(null)
                ->setCreatedAt(new \DateTime("now"));
            $em->persist($call);
            //dump("done");
        }
        else
        {
            $task = $em->getRepository("ModelBundle:Task")->loadTaskForOriginator($originator);
            if ($task !== null)
            {
                $route = $em->getRepository("ModelBundle:Route")->loadRouteForPartnership($customer, $task->getOffer()->getOwner());
                if ($route !== null) $task = null;
            }
            if ($task !== null)
            {
                $master_phone = $task->getOffer()->getPhone();
                $terminator = $em->getRepository("ModelBundle:Dongle")->suggestTerminator($master_phone, $originator);
                if ($terminator !== null)
                {
                    $route = (new Route())
                        ->setCustomerNumber($customer)
                        ->setMasterPhone($master_phone)
                        ->setOriginator($originator)
                        ->setTerminator($terminator)
                        ->setTask($task)
                        ->setState(Route::STATE_ACTIVE)
                        ->setExpiredAt((new \DateTime("now"))->add(new \DateInterval("P14D")));
                    $al = rand(30, 600);
                    $dl = $al + rand(6, 20);
                    $call = (new Call())
                        ->setId(uniqid("", true))
                        ->setRoute($route)
                        ->setDirection(Call::DIRECTION_RG)
                        ->setDialLength($dl)
                        ->setAnswerLength($al)
                        ->setResult(Call::RESULT_ANSWER)
                        ->setRecord(null)
                        ->setCreatedAt(new \DateTime("now"));
                    $em->persist($route);
                    $em->persist($call);
                    //dump("done");
                }
                else
                {
                    $route = (new Route())
                        ->setCustomerNumber($customer)
                        ->setMasterPhone(null)
                        ->setOriginator($originator)
                        ->setTerminator(null)
                        ->setTask(null)
                        ->setState(Route::STATE_ORPHAN)
                        ->setExpiredAt(new \DateTime("now"));
                    $call = (new Call())
                        ->setId(uniqid("", true))
                        ->setRoute($route)
                        ->setDirection(Call::DIRECTION_RG)
                        ->setDialLength(0)
                        ->setAnswerLength(0)
                        ->setResult(Call::RESULT_CANCEL)
                        ->setRecord(null)
                        ->setCreatedAt(new \DateTime("now"));
                    $em->persist($route);
                    $em->persist($call);
                    //dump("done");
                }
            }
            else
            {
                $route = (new Route())
                    ->setCustomerNumber($customer)
                    ->setMasterPhone(null)
                    ->setOriginator($originator)
                    ->setTerminator(null)
                    ->setTask(null)
                    ->setState(Route::STATE_ORPHAN)
                    ->setExpiredAt(new \DateTime("now"));
                $call = (new Call())
                    ->setId(uniqid("", true))
                    ->setRoute($route)
                    ->setDirection(Call::DIRECTION_RG)
                    ->setDialLength(0)
                    ->setAnswerLength(0)
                    ->setResult(Call::RESULT_CANCEL)
                    ->setRecord(null)
                    ->setCreatedAt(new \DateTime("now"));
                $em->persist($route);
                $em->persist($call);
                //dump("done");
            }
        }
        $em->flush();
        return;
    }
}
