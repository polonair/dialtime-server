<?php 

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Workers\UpdateSpreadsWorker;

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

        /* загружаем кампании и предложения */
    	$campaigns = $em->getRepository("ModelBundle:Campaign")->loadActive();
    	$offers = $em->getRepository("ModelBundle:Offer")->loadActive();

        /* загружаем категории и локации в индексированные идентификаторами массивы */
    	$locations = $em->getRepository("ModelBundle:Location")->loadIndexed();
    	$categories = $em->getRepository("ModelBundle:Category")->loadIndexed();

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
                    /* добавляем разность между спросом и предложением в сырую область массива данных */
    				$data[$campaign->getCategory()->getId()][$campaign->getLocation()->getId()]["raw"][] = 
    					$offer->getAsk() - $campaign->getBid();
    			}
    		}
    	}

        /* загружаем имеющиеся спреды в индексированную матрицу */
    	$spreads = $em->getRepository("ModelBundle:Spread")->loadMatrix();

    	foreach ($data as $category_id => $dim1) 
    	{
    		foreach($dim1 as $location_id => $dim2)
    		{
    			$raw = $dim2["raw"];
    			$max = 0;
    			$spread = 0;
    			foreach($raw as $k => $v)
    			{
    				$sum = 0;
    				foreach ($raw as $key => $value) if ($value >= $v) $sum += $value;
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
    	$data;

    	$em->flush();
    }
    private function compatible($em, Campaign $campaign, Offer $offer)
    {
    	//time rubric balance
    	return true;
    }
}
