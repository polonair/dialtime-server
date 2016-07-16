<?php

namespace Polonairs\Dialtime\ServerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Polonairs\Dialtime\ModelBundle\Entity\Admin;
use Polonairs\Dialtime\ModelBundle\Entity\User;

class FirstCredentialCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('dialtime:server:first-credentials')
            ->setDescription('Creates first admin credentials');
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	$em = $this->getContainer()->get('doctrine')->getManager();
    	$u = $em->getRepository("Polonairs\\Dialtime\\ModelBundle\\Entity\\User")->findAll();
    	$uv = $em->getRepository("Polonairs\\Dialtime\\ModelBundle\\Entity\\UserVersion")->findAll();
    	$a = $em->getRepository("Polonairs\\Dialtime\\ModelBundle\\Entity\\Admin")->findAll();
    	$av = $em->getRepository("Polonairs\\Dialtime\\ModelBundle\\Entity\\AdminVersion")->findAll();

    	if (count($u) === 0 && count($uv) === 0 && count($a) === 0 && count($av) === 0 )
    	{
    		$password = "password";
    		$user = (new User())->setUsername("admin");
    		$admin = (new Admin())->setUser($user);

    		$encoded = '$2a$12$ZbnkWdQg8to6cDw8EbkUIugaAn48vHpTUDIoL5tTlINopFBtV3vNy';//$this->getContainer()->get('security.password_encoder')->encodePassword($admin, $password);
            $user->setPassword($encoded);
            
			$em->persist($user);
			$em->persist($admin);
			$em->flush();

        	$output->writeln("First administer credentials created.");
        	$output->writeln("You have to change credetials as soon as you can!");
    	}
    	else
    	{
        	$output->writeln("Cannot create new user, database already contains some.");
    	}
    }
}