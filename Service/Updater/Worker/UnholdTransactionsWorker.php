<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Worker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class UnholdTransactionsWorker
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
		$held = $em->getRepository("ModelBundle:Transaction")->loadHeld();
		foreach($held as $transaction)
		{
			if ($transaction->getAutoClose() !== null && $transaction->getAutoCancel() === null)
			{
				if ((new \DateTime("now"))->getTimestamp() > $transaction->getAutoClose()->getTimestamp())
				{
					$em->getRepository("ModelBundle:Transaction")->doApply($transaction);
				}
			}
			else if ($transaction->getAutoClose() === null && $transaction->getAutoCancel() !== null)
			{
				if ((new \DateTime("now"))->getTimestamp() > $transaction->getAutoCancel()->getTimestamp())
				{
					$em->getRepository("ModelBundle:Transaction")->doCancel($transaction);
				}				
			}
		}
	}
}
