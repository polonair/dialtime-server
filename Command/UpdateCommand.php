<?php

namespace Polonairs\Dialtime\ServerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Polonairs\Dialtime\CommonBundle\Entity\Admin;
use Polonairs\Dialtime\CommonBundle\Entity\User;

class UpdateCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('dialtime:server:update')
            ->setDescription('Update system state');
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	$id = $this->getContainer()->getParameter("server_id");
    	$result = $this->getContainer()->get('updater')->updateAll($id);
    	$output->writeln($result);
    	$output->writeln("Update done");
    }
}