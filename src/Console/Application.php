<?php
declare(strict_types=1);

namespace Pac\Console;

use Pac\App\PacKernel;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends BaseApplication
{
    private $kernel;
    private $commandsRegistered = false;

    public function __construct(PacKernel $kernel)
    {
        $this->kernel = $kernel;
        parent::__construct('PAC', PacKernel::VERSION);
        $this->getDefinition()->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The environment name', $kernel->getEnvironment()));
        $this->getDefinition()->addOption(new InputOption('--no-debug', null, InputOption::VALUE_NONE, 'Switches off debug mode'));
    }

    public function getKernel(): PacKernel
    {
        return $this->kernel;
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $this->kernel->boot();
        $container = $this->kernel->getContainer();
        foreach ($this->all() as $command) {
            if ($command instanceof ContainerAwareInterface) {
                $command->setContainer($container);
            }
        }
//        $this->setDispatcher($container->get('event_dispatcher'));

        return parent::doRun($input, $output);
    }
    /**
     * {@inheritdoc}
     */
    public function find($name)
    {
        $this->registerCommands();
        return parent::find($name);
    }
    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        $this->registerCommands();
        return parent::get($name);
    }
    /**
     * {@inheritdoc}
     */
    public function all($namespace = null)
    {
        $this->registerCommands();
        return parent::all($namespace);
    }
    /**
     * {@inheritdoc}
     */
    public function getLongVersion()
    {
        return parent::getLongVersion().sprintf(' (kernel: <comment>%s</>, env: <comment>%s</>, debug: <comment>%s</>)', $this->kernel->getName(), $this->kernel->getEnvironment(), $this->kernel->isDebug() ? 'true' : 'false');
    }

    protected function registerCommands()
    {
        if ($this->commandsRegistered) {
            return;
        }
        $this->commandsRegistered = true;
        $this->kernel->boot();
        $container = $this->kernel->getContainer();

        if ($container->has('console.commands')) {
            foreach ($container->get('console.commands') as $id) {
                $this->add($container->get($id));
            }
        }
    }
}
