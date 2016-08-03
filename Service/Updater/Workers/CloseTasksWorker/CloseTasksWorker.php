<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\CloseTasksWorker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ModelBundle\Entity\Task;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class CloseTasksWorker
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
        
        $tasks = $em->getRepository("ModelBundle:Task")->loadActive();
        
        foreach($tasks as $task)
        {
            if (!$this->compatible($task)) $this->close($task);
        }
        
        $em->flush();
	}
    private function compatible(Task $task)
    {
        return true;
    }
}
