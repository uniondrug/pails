<?php
namespace Pails\Console;

use Pails\InjectableTrait;
use Phalcon\Di\InjectionAwareInterface;
use Phalcon\Events\EventsAwareInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class Command
 *
 * @property \Phalcon\Mvc\Dispatcher|\Phalcon\Mvc\DispatcherInterface                                  $dispatcher
 * @property \Phalcon\Mvc\Router|\Phalcon\Mvc\RouterInterface                                          $router
 * @property \Phalcon\Mvc\Url|\Phalcon\Mvc\UrlInterface                                                $url
 * @property \Phalcon\Http\Request|\Phalcon\Http\RequestInterface                                      $request
 * @property \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface                                    $response
 * @property \Phalcon\Http\Response\Cookies|\Phalcon\Http\Response\CookiesInterface                    $cookies
 * @property \Phalcon\Filter|\Phalcon\FilterInterface                                                  $filter
 * @property \Phalcon\Flash\Direct                                                                     $flash
 * @property \Phalcon\Flash\Session                                                                    $flashSession
 * @property \Phalcon\Session\Adapter\Files|\Phalcon\Session\Adapter|\Phalcon\Session\AdapterInterface $session
 * @property \Phalcon\Events\Manager|\Phalcon\Events\ManagerInterface                                  $eventsManager
 * @property \Phalcon\Db\AdapterInterface                                                              $db
 * @property \Phalcon\Security                                                                         $security
 * @property \Phalcon\Crypt|\Phalcon\CryptInterface                                                    $crypt
 * @property \Phalcon\Tag                                                                              $tag
 * @property \Phalcon\Escaper|\Phalcon\EscaperInterface                                                $escaper
 * @property \Phalcon\Annotations\Adapter\Memory|\Phalcon\Annotations\Adapter                          $annotations
 * @property \Phalcon\Mvc\Model\Manager|\Phalcon\Mvc\Model\ManagerInterface                            $modelsManager
 * @property \Phalcon\Mvc\Model\MetaData\Memory|\Phalcon\Mvc\Model\MetadataInterface                   $modelsMetadata
 * @property \Phalcon\Mvc\Model\Transaction\Manager|\Phalcon\Mvc\Model\Transaction\ManagerInterface    $transactionManager
 * @property \Phalcon\Assets\Manager                                                                   $assets
 * @property \Phalcon\Session\Bag|\Phalcon\Session\BagInterface                                        $persistent
 * @property \Phalcon\Mvc\View|\Phalcon\Mvc\ViewInterface                                              $view
 * @property \Pails\Container|\Phalcon\Di                                                              $di
 * @property \Pails\Plugins\ApiResponse                                                                $apiResponse
 * @property \Pails\Plugins\ApiClient                                                                  $apiClient
 * @property \Pails\Plugins\Fractal                                                                    $fractal
 * @property \Pails\Pluralizer                                                                         $inflector
 * @property \Pails\Plugins\Config                                                                     $config
 * @property \Pails\Exception\Handler                                                                  $exceptionHandler
 * @property \Phalcon\Security\Random                                                                  $random
 * @property \Phalcon\Cache\BackendInterface                                                           $fileCache
 * @property \Phalcon\Cache\BackendInterface                                                           $redisCache
 * @property \Phalcon\Cache\BackendInterface                                                           $memcachedCache
 * @property \Phalcon\Cache\Multiple                                                                   $cache
 * @property \Phalcon\Logger\Adapter\File                                                              $logger
 * @property \Phalcon\Logger\Adapter\File                                                              $errorLogger
 * @property \GuzzleHttp\Client                                                                        $httpClient
 * @property \AliyunMNS\Client                                                                         $mns
 * @property \OSS\OssClient                                                                            $oss
 * @property \League\Flysystem\FilesystemInterface                                                     $storage
 * @property \League\Flysystem\FilesystemInterface                                                     $localFs
 * @property \League\Flysystem\FilesystemInterface                                                     $ossFs
 * @property \League\Flysystem\MountManager                                                            $filesystem
 * @property \League\OAuth2\Client\Provider\GenericProvider                                            $authClient
 * @property \Redis                                                                                    $redis
 * @property \Pails\Queue\Queue                                                                        $queue
 *
 * @package Pails\Console
 */
abstract class Command extends SymfonyCommand implements InjectionAwareInterface, EventsAwareInterface
{
    use InjectableTrait;

    /**
     * The input interface implementation.
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * The output interface implementation.
     *
     * @var \Pails\Console\OutputStyle
     */
    protected $output;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description;

    /**
     * The default verbosity of output commands.
     *
     * @var int
     */
    protected $verbosity = OutputInterface::VERBOSITY_NORMAL;

    /**
     * The mapping between human readable verbosity levels and Symfony's OutputInterface.
     *
     * @var array
     */
    protected $verbosityMap = [
        'v'      => OutputInterface::VERBOSITY_VERBOSE,
        'vv'     => OutputInterface::VERBOSITY_VERY_VERBOSE,
        'vvv'    => OutputInterface::VERBOSITY_DEBUG,
        'quiet'  => OutputInterface::VERBOSITY_QUIET,
        'normal' => OutputInterface::VERBOSITY_NORMAL,
    ];

    /**
     * Create a new console command instance.
     *
     * @param null $name
     */
    public function __construct($name = null)
    {
        // We will go ahead and set the name, description, and parameters on console
        // commands just to make things a little easier on the developer. This is
        // so they don't have to all be manually specified in the constructors.
        if (isset($this->signature)) {
            $this->configureUsingFluentDefinition();
        } else {
            parent::__construct($this->name);
        }
        $this->setDescription($this->description);
        if (!isset($this->signature)) {
            $this->specifyParameters();
        }
    }

    /**
     * Configure the console command using a fluent definition.
     */
    protected function configureUsingFluentDefinition()
    {
        list($name, $arguments, $options) = Parser::parse($this->signature);
        parent::__construct($name);
        foreach ($arguments as $argument) {
            $this->getDefinition()->addArgument($argument);
        }
        foreach ($options as $option) {
            $this->getDefinition()->addOption($option);
        }
    }

    /**
     * Specify the arguments and options on the command.
     */
    protected function specifyParameters()
    {
        // We will loop through all of the arguments and options for the command and
        // set them all on the base command instance. This specifies what can get
        // passed into these commands as "parameters" to control the execution.
        foreach ($this->getArguments() as $arguments) {
            call_user_func_array([$this, 'addArgument'], $arguments);
        }
        foreach ($this->getOptions() as $options) {
            call_user_func_array([$this, 'addOption'], $options);
        }
    }

    /**
     * Run the console command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = new OutputStyle($input, $output);

        return parent::run($input, $output);
    }

    /**
     * Execute the console command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return mixed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // set env through argument --env
        if ($input->hasOption('env')) {
            $env = $input->getOption('env');
            putenv("APP_ENV=$env");
        }

        // call method defined in Command.
        return call_user_func_array([$this, 'handle'], []);
    }

    /**
     * Call another console command.
     *
     * @param string $command
     * @param array  $arguments
     *
     * @return int
     */
    public function call($command, array $arguments = [])
    {
        $instance = $this->getApplication()->find($command);
        $arguments['command'] = $command;

        return $instance->run(new ArrayInput($arguments), $this->output);
    }

    /**
     * Call another console command silently.
     *
     * @param string $command
     * @param array  $arguments
     *
     * @return int
     */
    public function callSilent($command, array $arguments = [])
    {
        $instance = $this->getApplication()->find($command);
        $arguments['command'] = $command;

        return $instance->run(new ArrayInput($arguments), new NullOutput);
    }

    /**
     * Determine if the given argument is present.
     *
     * @param string|int $name
     *
     * @return bool
     */
    public function hasArgument($name)
    {
        return $this->input->hasArgument($name);
    }

    /**
     * Get the value of a command argument.
     *
     * @param string $key
     *
     * @return string|array
     */
    public function argument($key = null)
    {
        if (is_null($key)) {
            return $this->input->getArguments();
        }

        return $this->input->getArgument($key);
    }

    /**
     * Determine if the given option is present.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasOption($name)
    {
        return $this->input->hasOption($name);
    }

    /**
     * Get the value of a command option.
     *
     * @param string $key
     *
     * @return string|array
     */
    public function option($key = null)
    {
        if (is_null($key)) {
            return $this->input->getOptions();
        }

        return $this->input->getOption($key);
    }

    /**
     * Confirm a question with the user.
     *
     * @param string $question
     * @param bool   $default
     *
     * @return bool
     */
    public function confirm($question, $default = false)
    {
        return $this->output->confirm($question, $default);
    }

    /**
     * Prompt the user for input.
     *
     * @param string $question
     * @param string $default
     *
     * @return string
     */
    public function ask($question, $default = null)
    {
        return $this->output->ask($question, $default);
    }

    /**
     * Prompt the user for input with auto completion.
     *
     * @param string $question
     * @param array  $choices
     * @param string $default
     *
     * @return string
     */
    public function anticipate($question, array $choices, $default = null)
    {
        return $this->askWithCompletion($question, $choices, $default);
    }

    /**
     * Prompt the user for input with auto completion.
     *
     * @param string $question
     * @param array  $choices
     * @param string $default
     *
     * @return string
     */
    public function askWithCompletion($question, array $choices, $default = null)
    {
        $question = new Question($question, $default);
        $question->setAutocompleterValues($choices);

        return $this->output->askQuestion($question);
    }

    /**
     * Prompt the user for input but hide the answer from the console.
     *
     * @param string $question
     * @param bool   $fallback
     *
     * @return string
     */
    public function secret($question, $fallback = true)
    {
        $question = new Question($question);
        $question->setHidden(true)->setHiddenFallback($fallback);

        return $this->output->askQuestion($question);
    }

    /**
     * Give the user a single choice from an array of answers.
     *
     * @param string $question
     * @param array  $choices
     * @param string $default
     * @param mixed  $attempts
     * @param bool   $multiple
     *
     * @return string
     */
    public function choice($question, array $choices, $default = null, $attempts = null, $multiple = null)
    {
        $question = new ChoiceQuestion($question, $choices, $default);
        $question->setMaxAttempts($attempts)->setMultiselect($multiple);

        return $this->output->askQuestion($question);
    }

    /**
     * Format input to textual table.
     *
     * @param array  $headers
     * @param array  $rows
     * @param string $style
     */
    public function table(array $headers, $rows, $style = 'default')
    {
        $table = new Table($this->output);
        $table->setHeaders($headers)->setRows($rows)->setStyle($style)->render();
    }

    /**
     * Write a string as information output.
     *
     * @param string          $string
     * @param null|int|string $verbosity
     */
    public function info($string, $verbosity = null)
    {
        $this->line($string, 'info', $verbosity);
    }

    /**
     * Write a string as standard output.
     *
     * @param string          $string
     * @param string          $style
     * @param null|int|string $verbosity
     */
    public function line($string, $style = null, $verbosity = null)
    {
        $styled = $style ? "<$style>$string</$style>" : $string;
        $this->output->writeln($styled, $this->parseVerbosity($verbosity));
    }

    /**
     * Write a string as comment output.
     *
     * @param string          $string
     * @param null|int|string $verbosity
     */
    public function comment($string, $verbosity = null)
    {
        $this->line($string, 'comment', $verbosity);
    }

    /**
     * Write a string as question output.
     *
     * @param string          $string
     * @param null|int|string $verbosity
     */
    public function question($string, $verbosity = null)
    {
        $this->line($string, 'question', $verbosity);
    }

    /**
     * Write a string as error output.
     *
     * @param string          $string
     * @param null|int|string $verbosity
     */
    public function error($string, $verbosity = null)
    {
        $this->line($string, 'error', $verbosity);
    }

    /**
     * Write a string as warning output.
     *
     * @param string          $string
     * @param null|int|string $verbosity
     */
    public function warn($string, $verbosity = null)
    {
        if (!$this->output->getFormatter()->hasStyle('warning')) {
            $style = new OutputFormatterStyle('yellow');
            $this->output->getFormatter()->setStyle('warning', $style);
        }
        $this->line($string, 'warning', $verbosity);
    }

    /**
     * Get the verbosity level in terms of Symfony's OutputInterface level.
     *
     * @param string|int $level
     *
     * @return int
     */
    protected function parseVerbosity($level = null)
    {
        if (isset($this->verbosityMap[$level])) {
            $level = $this->verbosityMap[$level];
        } elseif (!is_int($level)) {
            $level = $this->verbosity;
        }

        return $level;
    }

    /**
     * Set the verbosity level.
     *
     * @param string|int $level
     */
    protected function setVerbosity($level)
    {
        $this->verbosity = $this->parseVerbosity($level);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }

    /**
     * Get the output implementation.
     *
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }
}
