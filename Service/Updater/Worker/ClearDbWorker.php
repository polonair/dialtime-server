<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Worker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class ClearDbWorker
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
        // clear old data (archive old versions)
	}
}
