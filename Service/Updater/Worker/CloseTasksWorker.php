<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Worker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ModelBundle\Entity\Task;
use Polonairs\Dialtime\ModelBundle\Entity\Offer;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class CloseTasksWorker
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
        
        $tasks = $em->getRepository("ModelBundle:Task")->loadActive();
        
        foreach($tasks as $task)
        {
            if (!$this->offerIsActive($task))
            {
                $this->close($task, Task::REASON_USER);
            }
            else if ($this->taskIsTimeOuted($em, $task))
            {
                $this->close($task, Task::REASON_TO);
            }
            else if (!$this->correctPrice($em, $task))
            {
                $this->close($task, Task::REASON_PO);
            }
        }
        
        $em->flush();
	}
    private function offerIsActive(Task $task)
    {
        return ($task->getOffer()->getState() !== Offer::STATE_OFF);
    }
    private function taskIsTimeOuted($em, Task $task)
    {
        $now = ((date("N")-1)*1440) + (date("H")*60) + date("i");
        return !$em->getRepository("ModelBundle:Offer")->isOfferActual($task->getOffer(), $now);
    }
    private function correctPrice($em, Task $task)
    {
        $spread = $em->getRepository("ModelBundle:Spread")->loadByCampaign($task->getCampaign());
        $ask = $task->getOffer()->getAsk();
        $bid = $task->getCampaign()->getBid();
        if (($ask-$bid) < $spread->getValue()) return false;
        if ($ask > $task->getOffer()->getOwner()->getUser()->getMainAccount()->getBalance()) return false;
        return true;
    }
    private function close(Task $task, $reason)
    {
        $task->setState(Task::STATE_CLOSED);
        $task->setCloseReason($reason);
    }
}
