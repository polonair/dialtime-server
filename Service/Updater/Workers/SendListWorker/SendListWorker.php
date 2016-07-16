<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\SendListWorker;

use Polonairs\Dialtime\CommonBundle\Entity\ServerJob;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class SendListWorker
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
        // send list (email, sms and so on)
	}
}
