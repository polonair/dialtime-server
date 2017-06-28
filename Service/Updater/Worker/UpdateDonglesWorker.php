<?php

namespace Polonairs\Dialtime\ServerBundle\Service\Updater\Worker;

class UpdateDonglesWorker
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
        $dongle = $em->getRepository("ModelBundle:Dongle")->loadEarlyUpdatedDongle();

    	// touch dongle
        $touchUrl = sprintf("https://sm.megafon.ru/sm/client/routing/set?login=%s@multifon.ru&password=%s&routing=1", 
                            $dongle->getNumber(), $dongle->getPassVoice());
        $ch = curl_init($touchUrl);
        curl_setopt($ch, CURLOPT_SSLVERSION,3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        // check balance
        $balanceUrl = sprintf("https://sm.megafon.ru/sm/client/balance/?login=%s@multifon.ru&password=%s&routing=1", 
                            $dongle->getNumber(), $dongle->getPassVoice());
        $ch = curl_init($balanceUrl);
        curl_setopt($ch, CURLOPT_SSLVERSION,3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        $response = new SimpleXMLElement($response);
        if ($response->result->code == 200) $dongle->setBalance($response->balance);
    }
}
