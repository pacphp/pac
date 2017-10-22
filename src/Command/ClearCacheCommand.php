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
        $cacheDirectory = $this->getContainer()->getParameter('kernel.cache_dir');

        $this->clearDirectory($cacheDirectory);

        $output->writeln('<info>The cache was cleared</info>');
    }

    private function clearDirectory(string $directory)
    {
        $paths = glob($directory . '/*');
        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);

                continue;
            }
            if (is_dir($path)) {
                $this->clearDirectory($path);
                rmdir($path);
            }
        }
    }
}
