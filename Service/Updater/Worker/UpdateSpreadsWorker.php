<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Worker;

use Polonairs\Dialtime\ModelBundle\Entity\ServerJob;
use Polonairs\Dialtime\ModelBundle\Entity\Offer;
use Polonairs\Dialtime\ModelBundle\Entity\Campaign;
use Polonairs\Dialtime\ModelBundle\Entity\Spread;
use Polonairs\Dialtime\ServerBundle\Service\Updater\WorkerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

class UpdateSpreadsWorker
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

        /* загружаем кампании и предложения */
    	$campaigns = $em->getRepository("ModelBundle:Campaign")->loadActive();
    	$offers = $em->getRepository("ModelBundle:Offer")->loadActive();

        //dump($campaigns);
        //dump($offers);

        /* загружаем категории и локации в индексированные идентификаторами массивы */
    	$locations = $em->getRepository("ModelBundle:Location")->loadIndexed();
    	$categories = $em->getRepository("ModelBundle:Category")->loadIndexed();

        //dump($locations);
        //dump($categories);

        /* подготавливаем массив с данными */
    	$data = [];

        /* перебираем всевозможные пары кампаний и предложений */
    	foreach($campaigns as $campaign)
    	{
    		foreach ($offers as $offer) 
    		{
                /* если пара предложения и кампании проходима */
    			if($this->compatible($em, $campaign, $offer))
    			{
                    //echo "\r\nworks: c".$campaign->getId().", o".$offer->getId()."\r\n";
                    /* добавляем разность между спросом и предложением в сырую область массива данных */
    				$data[$campaign->getCategory()->getId()][$campaign->getLocation()->getId()]["raw"][] = 
    					$offer->getAsk() - $campaign->getBid();
    			}
    		}
    	}
        //dump($data);

        /* загружаем имеющиеся спреды в индексированную матрицу */
    	$spreads = $em->getRepository("ModelBundle:Spread")->loadMatrix();

    	foreach ($data as $category_id => $dim1) 
    	{
    		foreach($dim1 as $location_id => $dim2)
    		{
    			$raw = $dim2["raw"];
    			$max = 0;
    			$spread = 0;
    			foreach($raw as $v)
    			{
    				$sum = 0;
    				foreach ($raw as $value) if ($value >= $v) $sum += $value;
    				if ($sum >= $max) 
    				{
    					$max = $sum;
    					$spread = $v;
    				}
    			}
    			$data[$category_id][$location_id]["spread"] = $spread;
    			if (array_key_exists($category_id, $spreads) && array_key_exists($location_id, $spreads[$category_id]))
    			{
    				$spreads[$category_id][$location_id]->setValue($spread);
    			}
    			else
    			{
    				$spreads[$category_id][$location_id] = (new Spread())
    					->setCategory($categories[$category_id])
    					->setLocation($locations[$location_id])
    					->setValue($spread);
    				$em->persist($spreads[$category_id][$location_id]);
    			}
    		}
    	}

    	$em->flush();
    }
    private function compatible_deprecated($e, Campaign $c, Offer $o)
    {
        $now = ((date("N")-1)*1440) + (date("H")*60) + date("i");

        return (
            ($o->getAsk() < $o->getOwner()->getUser()->getMainAccount()->getBalance()) &&
            (($o->getAsk() > $c->getBid())) &&
            ($e->getRepository("ModelBundle:Offer")->isOfferActual($o, $now)) &&
            ($e->getRepository("ModelBundle:Category")->isChildOrSame($o->getCategory(), $c->getCategory())) &&
            ($e->getRepository("ModelBundle:Location")->isChildOrSame($o->getLocation(), $c->getLocation())));
    }
    private function compatible($e, Campaign $c, Offer $o)
    {
        $time = time() + $o->getSchedule()->getTimezone()*60;
        $now = ((date("N", $time)-1)*1440) + (date("H", $time)*60) + date("i", $time);

        return (
            ($o->getAsk() < $o->getOwner()->getUser()->getMainAccount()->getBalance()) &&
            (($o->getAsk() > $c->getBid())) &&
            ($e->getRepository("ModelBundle:Offer")->isOfferActual_new($o, $now)) &&
            ($e->getRepository("ModelBundle:Category")->isChildOrSame($o->getCategory(), $c->getCategory())) &&
            ($e->getRepository("ModelBundle:Location")->isChildOrSame($o->getLocation(), $c->getLocation())));
    }
}
