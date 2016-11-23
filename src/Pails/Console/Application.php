<?php
namespace Pails\Console;

use Pails\ApplicationInterface;
use Pails\Console\Commands;
use Pails\ContainerInterface;
use Phalcon\Di;
use Phalcon\Di\InjectionAwareInterface;
use Phalcon\DiInterface;
use Symfony\Component\Console\Application as ApplicationBase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Pails console application.
 *
 */
abstract class Application extends ApplicationBase implements InjectionAwareInterface, ApplicationInterface
{
    /**
     * @var DiInterface
     */
    protected $di;

    /**
     * The output from the previous command.
     *
     * @var \Symfony\Component\Console\Output\BufferedOutput
     */
    protected $lastOutput;

    /**
     * Service Providers want to be injected
     *
     * @var array
     */
    protected $providers = [

    ];

    /**
     * Commands
     *
     * @var array
     */
    protected $commands = [

    ];

    protected $pailsCommands = [
        Commands\HelloWorldCommand::class
    ];

    /**
     * Class Constructor.
     *
     * Initialize the Pails console application.
     *
     * @param ContainerInterface|DiInterface $di
     * @internal param string $version The Application Version
     */
    public function __construct(ContainerInterface $di = null)
    {
        if ($di) {
            $this->setDI($di);
        } else {
            $this->setDI(Di::getDefault());
        }

        parent::__construct('Pails', $this->di->version());

        // For Phinx, set configuration file by default
        $this->getDefinition()->addOption(new InputOption('--configuration', '-c', InputOption::VALUE_REQUIRED, 'The configuration file to load'));
        array_push($_SERVER['argv'], '--configuration=config/database.yml');

        // Phinx commands wraps
        $this->addCommands(array(
            new Commands\Db\InitCommand(),
            new Commands\Db\BreakpointCommand(),
            new Commands\Db\CreateCommand(),
            new Commands\Db\MigrateCommand(),
            new Commands\Db\RollbackCommand(),
            new Commands\Db\SeedCreateCommand(),
            new Commands\Db\SeedRunCommand(),
            new Commands\Db\StatusCommand()
        ));

        // Pails commands
        $this->resolveCommands($this->pailsCommands);
    }

    /**
     * Runs the current application.
     *
     * @param InputInterface $input An Input instance
     * @param OutputInterface $output An Output instance
     * @return integer 0 if everything went fine, or an error code
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        // always show the version information except when the user invokes the help
        // command as that already does it
        if (false === $input->hasParameterOption(array('--help', '-h')) && null !== $input->getFirstArgument()) {
            $output->writeln($this->getLongVersion());
            $output->writeln('');
        }

        return parent::doRun($input, $output);
    }

    /**
     * Get the default input definitions for the applications.
     *
     * This is used to add the --env option to every available command.
     *
     * @return \Symfony\Component\Console\Input\InputDefinition
     */
    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption($this->getEnvironmentOption());

        return $definition;
    }

    /**
     * Get the global environment option for the definition.
     *
     * @return \Symfony\Component\Console\Input\InputOption
     */
    protected function getEnvironmentOption()
    {
        $message = 'The environment the command should run under.';

        return new InputOption('--env', null, InputOption::VALUE_OPTIONAL, $message);
    }

    /**
     * Run an Artisan console command by name.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @return int
     */
    public function call($command, array $parameters = [])
    {
        array_unshift($parameters, $command);

        $this->lastOutput = new BufferedOutput;

        $this->setCatchExceptions(false);

        $result = $this->run(new ArrayInput($parameters), $this->lastOutput);

        $this->setCatchExceptions(true);

        return $result;
    }

    /**
     * @return \Phalcon\DiInterface
     */
    public function getDI()
    {
        return $this->di;
    }

    /**
     * Sets the dependency injector
     *
     * @param mixed $dependencyInjector
     */
    public function setDI(DiInterface $dependencyInjector)
    {
        $this->di = $dependencyInjector;
    }

    /**
     * register services
     */
    public function boot()
    {
        $this->di->registerServices($this->providers);

        return $this;
    }

    public function init()
    {
        $this->resolveCommands($this->commands);

        return $this;
    }

    /**
     * @return mixed
     */
    public function handle()
    {
        $exitCode = $this->run();
        return $exitCode;
    }

    /**
     * Add a command, resolving through the application.
     *
     * @param  string  $command
     * @return \Symfony\Component\Console\Command\Command
     */
    public function resolve($command)
    {
        return $this->add($this->di->get($command));
    }

    /**
     * Resolve an array of commands through the application.
     *
     * @param  array|mixed  $commands
     * @return $this
     */
    public function resolveCommands($commands)
    {
        $commands = is_array($commands) ? $commands : func_get_args();

        foreach ($commands as $command) {
            $this->resolve($command);
        }

        return $this;
    }

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output()
    {
        return $this->lastOutput ? $this->lastOutput->fetch() : '';
    }
}
