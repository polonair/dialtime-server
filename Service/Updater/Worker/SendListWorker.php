<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Worker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ModelBundle\Entity\LetterSending;
use Polonairs\SmsiBundle\Smsi\SmsMessage;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class SendListWorker
{
    private $job = null;
    private $doctrine  = null;

	public function __construct(ServerJob $job, Doctrine $doctrine, $sms_sender) 
	{ 
        $this->job = $job;
        $this->doctrine = $doctrine;
        $this->sms_sender = $sms_sender;
	}
	public function doJob()
	{
		$em = $this->doctrine->getManager();

		$sendings = $em->getRepository("ModelBundle:LetterSending")->loadUnprocessed();

		foreach($sendings as $sending)
		{
			if ($sending->getDeliverType() === LetterSending::DELIVER_WITH_SMS)
			{
				$receiver = $em->getRepository("ModelBundle:Phone")->loadByOwner($sending->getReceiver());
				$sms = (new SmsMessage())
					->setTo($receiver[0]->getNumber())
					->setText($sending->getLetter()->getBody());
				$this->sms_sender->send($sms);
				$sending->setProcessed(new \DateTime("now"));
			}
		}
		$em->flush();
	}
}
