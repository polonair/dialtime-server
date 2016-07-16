<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\OpenTasksWorker;

use Polonairs\Dialtime\CommonBundle\Entity\ServerJob;
use Polonairs\Dialtime\CommonBundle\Entity\Campaign;
use Polonairs\Dialtime\CommonBundle\Entity\Task;
use Polonairs\Dialtime\CommonBundle\Entity\Offer;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class OpenTasksWorker
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
        
        $mrefpays = $em->getRepository("CommonBundle:Parameter")->loadArray("referral:pay:master");
        $prefpays = $em->getRepository("CommonBundle:Parameter")->loadArray("referral:pay:partner");
        
        $minmal_spread = 0;
        foreach($mrefpays as $v) $minimal_spread += $v;
        foreach($prefpays as $v) $minimal_spread += $v;
        
        $campaigns = $em->getRepository("CommonBundle:Campaign")->loadActive();
        $offers = $em->getRepository("CommonBundle:Offer")->loadActive();
        
        $tasks = $em->getRepository("CommonBundle:Task")->loadMatrix();
        $spreads = $em->getRepository("CommonBundle:Spread")->loadMatrix();
        
        foreach($campaigns as $ck => $campaign)
        {
            foreach($offers as $ok => $offer)
            {
                if ($this->compatible($campaign, $offer, $spreads))
                {
                    $master_price = $offer->getAsk();
                    $partner_price = $campaign->getBid();
                    $system_price = $offer->getAsk() - $campaign->getBid();
                    if (isset($tasks[$ck]) && isset($tasks[$ck][$ok]))
                    {
                        $tasks[$ck][$ok]
                            ->setMasterPrice($master_price)
                            ->setPartnerPrice($partner_price)
                            ->setSystemPrice($system_price);
                    }
                    else
                    {
                        $tasks[$ck][$ok] = (new Task())
                            ->setCampaign($campaign)
                            ->setOffer($offer)
                            ->setMasterPrice($master_price)
                            ->setPartnerPrice($partner_price)
                            ->setSystemPrice($system_price);
                        $em->persist($tasks[$ck][$ok]);
                    }
                }                
            }
        }
        $em->flush();
	}
    private function compatible(Campaign $c, Offer $o, $spreads)
    {
        return true;
    }
}
