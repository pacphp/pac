<?php
declare(strict_types=1);

namespace Pac\Command;

use Pac\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearCacheCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('cache:clear')
            ->setDescription('Clear the cache')
            ->setHelp('Clear the cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $files = glob($cacheDir . '/*');
        foreach ($files as $file) {
            if(is_file($file)) {
                unlink($file);
            }
        }
    }
}
