<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Worker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ModelBundle\Entity\Campaign;
use Polonairs\Dialtime\ModelBundle\Entity\Task;
use Polonairs\Dialtime\ModelBundle\Entity\Offer;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class OpenTasksWorker
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
        
        //$mrefpays = $em->getRepository("ModelBundle:Parameter")->loadArray("referral:pay:master");
        //$prefpays = $em->getRepository("ModelBundle:Parameter")->loadArray("referral:pay:partner");
        
        //$minmal_spread = 0;
        //foreach($mrefpays as $v) $minimal_spread += $v;
        //foreach($prefpays as $v) $minimal_spread += $v;
        
        $campaigns = $em->getRepository("ModelBundle:Campaign")->loadActive();
        $offers = $em->getRepository("ModelBundle:Offer")->loadActive();
        
        $tasks = $em->getRepository("ModelBundle:Task")->loadMatrix();
        $spreads = $em->getRepository("ModelBundle:Spread")->loadMatrix();

        $max = $min = -1;
        $rates = [];
        
        foreach($campaigns as $ck => $campaign)
        {
            foreach($offers as $ok => $offer)
            {
                if ($this->compatible($em, $campaign, $offer, $spreads))
                {
                    $master_rating = $offer->getOwner()->getUser()->getRateAccount()->getBalance();
                    $partner_rating = $campaign->getOwner()->getUser()->getRateAccount()->getBalance();
                    $comission = $spreads[$campaign->getCategory()->getId()][$campaign->getLocation()->getId()]->getValue();

                    $ask = $offer->getAsk() - ($master_rating*($offer->getAsk()-$campaign->getBid()-$comission)/($master_rating + $partner_rating));
                    $bid = $campaign->getBid()+($partner_rating*($offer->getAsk()-$campaign->getBid()-$comission)/($master_rating + $partner_rating));
                    if (isset($tasks[$ck]) && isset($tasks[$ck][$ok]))
                    {
                        $tasks[$ck][$ok]
                            ->setMasterPrice($ask)
                            ->setPartnerPrice($bid)
                            ->setSystemPrice($comission);
                    }
                    else
                    {
                        $tasks[$ck][$ok] = (new Task())
                            ->setCampaign($campaign)
                            ->setOffer($offer)
                            ->setMasterPrice($ask)
                            ->setPartnerPrice($bid)
                            ->setSystemPrice($comission);
                        $em->persist($tasks[$ck][$ok]);
                    }

                    // do rate

                    $rate_diff = abs($master_rating-$partner_rating);
                    $latestRoute = $em->getRepository("ModelBundle:Route")->loadLatestForOffer($offer);
                    $wait = 0;
                    if ($latestRoute !== null)
                    {
                        $wait = (new \DateTime("now"))->diff($latestRoute->getCreatedAt())->s;
                    }
                    $kmr = $pmr = $kpr = $ppr = $krd = $prd = $ka = $pa = $kb = $pb = $kw = $pw = 1;
                    $rate = 
                        ($kmr*pow($master_rating,  $pmr) + //+
                         $kpr*pow($partner_rating, $ppr) + //+
                         $krd*pow($rate_diff,      $prd) + //-
                         $ka *pow($ask,            $pa)  + //+
                         $kb *pow($bid,            $pb)  + //-
                         $kw *pow($wait,           $pw)) / //+
                        ($kmr + $kpr + $krd + $ka + $kb + $kw);
                    if ($min == $max && $min == -1) $min = $max = $rate;
                    else
                    {
                        if ($rate < $min) $min = $rate;
                        if ($rate > $max) $max = $rate;
                    }
                    $rates[$ck][$ok] = $rate;
                    //$tasks[$ck][$ok]->setRate($rate);
                }
                else
                {
                    $tasks[$ck][$ok] = null;               
                }                
            }
        }
        foreach($campaigns as $ck => $campaign)
        {
            foreach($offers as $ok => $offer)
            {
                if (isset($tasks[$ck]) && isset($tasks[$ck][$ok]) && $tasks[$ck][$ok] !== null)
                {
                    if ($max == $min)
                    {
                        $tasks[$ck][$ok]->setRate(1.0);
                    }
                    else
                    {
                        $tasks[$ck][$ok]->setRate(($rates[$ck][$ok]-$min)/($max-$min));
                        //$tasks[$ck][$ok]->setRate(($tasks[$ck][$ok]->getRate()-$min)/($max-$min));
                    }
                }         
            }
        }
        $em->flush();
	}
    private function compatible($e, Campaign $c, Offer $o, $spreads)
    {
        $now = ((date("N")-1)*1440) + (date("H")*60) + date("i");
        return (
            ($o->getAsk() < $o->getOwner()->getUser()->getMainAccount()->getBalance()) &&
            array_key_exists($c->getCategory()->getId(), $spreads) &&
            array_key_exists($c->getLocation()->getId(), $spreads[$c->getCategory()->getId()]) &&
            (($o->getAsk()-$c->getBid()) >= $spreads[$c->getCategory()->getId()][$c->getLocation()->getId()]->getValue()) &&
            $e->getRepository("ModelBundle:Offer")->isOfferActual($o, $now) && 
            $e->getRepository("ModelBundle:Category")->isChildOrSame($o->getCategory(), $c->getCategory()) &&
            $e->getRepository("ModelBundle:Location")->isChildOrSame($o->getLocation(), $c->getLocation()));
    }
}
