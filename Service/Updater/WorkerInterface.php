<?php

namespace Polonairs\Dialtime\ServerBundle\Service\Updater;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;

interface WorkerInterface
{
	public function doJob();
	public function setJob(ServerJob $job);
}