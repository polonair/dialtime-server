<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\BillWorker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ModelBundle\Entity\Route;
use Polonairs\Dialtime\ModelBundle\Entity\Call;
use Polonairs\Dialtime\ModelBundle\Entity\Task;
use Polonairs\Dialtime\ModelBundle\Entity\Account;
use Polonairs\Dialtime\ModelBundle\Entity\Transaction;
use Polonairs\Dialtime\ModelBundle\Entity\TransactionEntry;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class BillWorker
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
        $routes2Bill = $em->getRepository("ModelBundle:Call")->loadUnbilledCalls();
        //dump($routes2Bill);
        foreach($routes2Bill as $r) $this->billOut($r);
	}
    private function billOut(Call $call)
    {
        $em = $this->doctrine->getManager();

        $route = $call->getRoute();

        $transaction = (new Transaction())->setEvent(Transaction::EVENT_TRADE);
        $em->persist($transaction);
        
        $macc = $route->getMasterPhone()->getOwner()->getMainAccount();
        $mraccs = [];
        $pacc = $route->getOriginator()->getCampaign()->getOwner()->getUser()->getMainAccount();
        $praccs = [];
        $sacc = $em->getRepository("ModelBundle:Parameter")->loadValue("system.account");
        if ($sacc === null) return;
        $sacc = $em->getRepository("ModelBundle:Account")->findOneById($sacc);
        
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