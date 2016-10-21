<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Worker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ModelBundle\Entity\Event;
use Polonairs\Dialtime\ModelBundle\Entity\Letter;
use Polonairs\Dialtime\ModelBundle\Entity\LetterSending;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class EventProcessor
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
        $events = $em->getRepository("ModelBundle:Event")->loadUnprocessed();
        foreach($events as $event)
        {
            $this->process($em, $event);
        }
        $em->flush();
	}
    private function process($em, Event $event)
    {
        switch($event->getClass())
        {
            case Event::EVENT_CLASS_ROUTE: return $this->processRouteEvent($em, $event);
        }
    }
    private function processRouteEvent($em, Event $event)
    {
        if ($event->getType() === Event::EVENT_TYPE_CREATION)
        {
            $route = $em->getRepository("ModelBundle:Route")->findOneById($event->getObject());
            $letter = (new Letter())
                ->setTitle("")
                ->setBody("Вы получили звонок")
                ->setPriority(1);
            $sending = (new LetterSending())
                ->setReceiver($route->getMasterPhone()->getOwner())
                ->setSender(null)
                ->setLetter($letter)
                ->setSendOn(new \DateTime("now"))
                ->setDeliverType(LetterSending::DELIVER_WITH_SMS);
            $em->persist($letter);
            $em->persist($sending);
            $event->setProcessed(new \DateTime("now"));
        }
    }
}
