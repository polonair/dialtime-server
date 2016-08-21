<?php

namespace Polonairs\Dialtime\ServerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Polonairs\Dialtime\ModelBundle\Entity\Category;
use Polonairs\Dialtime\ModelBundle\Entity\Location;
use Polonairs\Dialtime\ModelBundle\Entity\Account;
use Polonairs\Dialtime\ModelBundle\Entity\User;
use Polonairs\Dialtime\ModelBundle\Entity\Admin;

class InitialFixtureCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('dialtime:server:initial-fixture')
            ->setDescription('Creates initial database entities credentials');
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	if ($this->dbEmpty())
    	{
    		$this->createCategories();
    		$this->createLocations();
    		$this->createAdministrator();
    		$this->createAccounts();
    	}
    	else
    	{
        	$output->writeln("Cannot create fixtures, database already contains data.");    		
    	}
    }
    protected function dbEmpty()
    {
    	$em = $this->getContainer()->get('doctrine')->getManager();
    	return 
    		count($em->getRepository("Polonairs\\Dialtime\\ModelBundle\\Entity\\Account")->findAll()) === 0 &&

    		count($em->getRepository("Polonairs\\Dialtime\\ModelBundle\\Entity\\Category")->findAll()) === 0 &&
    		count($em->getRepository("Polonairs\\Dialtime\\ModelBundle\\Entity\\CategoryVersion")->findAll()) === 0 &&
    		count($em->getRepository("Polonairs\\Dialtime\\ModelBundle\\Entity\\Location")->findAll()) === 0 &&
    		count($em->getRepository("Polonairs\\Dialtime\\ModelBundle\\Entity\\LocationVersion")->findAll()) === 0 &&
    		count($em->getRepository("Polonairs\\Dialtime\\ModelBundle\\Entity\\User")->findAll()) === 0 &&
    		count($em->getRepository("Polonairs\\Dialtime\\ModelBundle\\Entity\\UserVersion")->findAll()) === 0 &&
    		count($em->getRepository("Polonairs\\Dialtime\\ModelBundle\\Entity\\Admin")->findAll()) === 0 &&
    		count($em->getRepository("Polonairs\\Dialtime\\ModelBundle\\Entity\\AdminVersion")->findAll()) === 0
    		;
    }
    protected function createCategories()
    {
    	$em = $this->getContainer()->get('doctrine')->getManager();
    	$category = (new Category())
    		->setName("root_category")
    		->setDescription("")
    		->setParent(null);
    	$em->persist($category);
    	$em->flush();
    }
    protected function createLocations()
    {
    	$em = $this->getContainer()->get('doctrine')->getManager();
    	$location = (new Location())
    		->setName("root_location")
    		->setDescription("")
    		->setParent(null);
    	$em->persist($location);
    	$em->flush();
    }
    protected function createAdministrator()
    {
    	$em = $this->getContainer()->get('doctrine')->getManager();

    	$password = "password";
    	$user = (new User())->setUsername("admin");
    	$admin = (new Admin())->setUser($user);

    	$encoded = password_hash($password, PASSWORD_BCRYPT, array('cost' => 12));
    	$user->setPassword($encoded);

    	$em->persist($user);
    	$em->persist($admin);
    	$em->flush();
    }
    protected function createAccounts()
    {
    	$em = $this->getContainer()->get('doctrine')->getManager();
		$account = (new Account())
			->setName("SYSTEM_RUR")
			->setOwner(null)
			->setCurrency(Account::CURRENCY_RUR)
			->setState(Account::STATE_ACTIVE)
			->setCredit(99999999.9);
		$em->persist($account);

		$account = (new Account())
			->setName("SYSTEM_TCR")
			->setOwner(null)
			->setCurrency(Account::CURRENCY_TCR)
			->setState(Account::STATE_ACTIVE)
			->setCredit(99999999.9);
		$em->persist($account);

		$em->flush();
    }
}
