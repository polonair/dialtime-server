<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Worker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ModelBundle\Entity\Route;
use Polonairs\Dialtime\ModelBundle\Entity\Call;
use Polonairs\Dialtime\ModelBundle\Entity\Task;
use Polonairs\Dialtime\ModelBundle\Entity\Event;
use Polonairs\Dialtime\ModelBundle\Entity\Account;
use Polonairs\Dialtime\ModelBundle\Entity\Transaction;
use Polonairs\Dialtime\ModelBundle\Entity\TransactionEntry;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class BillWorker
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
        $routes2Bill = $em->getRepository("ModelBundle:Call")->loadUnbilledCalls();
        //dump($routes2Bill);
        foreach($routes2Bill as $r) 
        {
            $this->billOut($em, $r);
            $this->createEvent($em, $r);
        }
	}
    private function createEvent($em, Call $call)
    {
        $route = $call->getRoute();

        $event = (new Event())
            ->setType(Event::EVENT_TYPE_CREATION)
            ->setClass(Event::EVENT_CLASS_ROUTE)
            ->setObject($route->getId())
            ->setVersion($route->getActual()->getId());
        $em->persist($event);
        $em->flush();
    }
    private function billOut($em, Call $call)
    {
        $route = $call->getRoute();

        $transaction = (new Transaction())->setEvent(Transaction::EVENT_TRADE)->setAutoClose($call->getCreatedAt()->add(new \DateInterval('P3D')));
        $em->persist($transaction);
        
        $macc = $route->getMasterPhone()->getOwner()->getMainAccount();
        $mraccs = [];
        $pacc = $route->getOriginator()->getCampaign()->getOwner()->getUser()->getMainAccount();
        $praccs = [];
        $sacc = $em->getRepository("ModelBundle:Account")->findOneByName("SYSTEM_RUR");
        
        $entry_m = (new TransactionEntry())
            ->setTransaction($transaction)
            ->setFrom($macc)
            ->setTo($sacc)
            ->setRole(TransactionEntry::ROLE_BUYER)
            ->setAmount($route->getTask()->getMasterPrice());
        $em->persist($entry_m);

        $entry_p = (new TransactionEntry())
            ->setTransaction($transaction)
            ->setFrom($sacc)
            ->setTo($pacc)
            ->setRole(TransactionEntry::ROLE_SELLER)
            ->setAmount($route->getTask()->getPartnerPrice());
        $em->persist($entry_p);
            
        $call->setTransaction($transaction);
        $task = $route->getTask();        
        $task->setState(Task::STATE_CLOSED);
        $task->setCloseReason(Task::REASON_RG);

        $em->flush();

        $em->getRepository("ModelBundle:Transaction")->doHold($transaction);
        //$em->getRepository("ModelBundle:Transaction")->doApply($transaction);
    }
}
