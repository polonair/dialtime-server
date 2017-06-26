<?php

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Worker;

class SyncForbidsWorker
{
    private $job = null;
    private $doctrine  = null;

    public function __construct(ServerJob $job, Doctrine $doctrine)
    {
        $this->job = $job;
        $this->doctrine = $doctrine;
    }
    public function doJob() { }
}
