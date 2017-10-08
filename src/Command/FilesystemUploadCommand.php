<?php
declare(strict_types=1);

namespace Pac\Command;

use League\Flysystem\Filesystem;
use Pac\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FilesystemUploadCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('filesystem:upload')
            ->setDescription('Upload a file')
            ->setHelp('Upload a file')
            ->addArgument('file', InputArgument::REQUIRED, 'The location of the file to be uploaded');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Filesystem $filesystem */
        $filesystem = $this->getContainer()->get('filesystem');
        $file = $input->getArgument('file');
        $fileParts = pathinfo($file);
        $filename = $fileParts['basename'];
        $output->writeln("Uploading $filename");
        if (false === $contents = file_get_contents($file)) {
            $output->writeln("<error>There was a problem reading $file</error>");
        }
        if ($filesystem->write($filename, $contents)) {
            $output->writeln("<info>$filename was successfully uploaded</info>");
        } else {
            $output->writeln("<error>There was a problem uploading $filename</error>");
        }
    }
}
