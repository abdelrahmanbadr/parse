<?php

namespace Psecio\Parse\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Psecio\Parse\Subscriber\ExitCodeCatcher;
use Psecio\Parse\Subscriber\ConsoleStandard;
use Psecio\Parse\Subscriber\ConsoleVerbose;
use Psecio\Parse\Subscriber\ConsoleDebug;
use Psecio\Parse\Subscriber\ConsoleReport;
use Psecio\Parse\Subscriber\Xml;
use Psecio\Parse\RuleFactory;
use Psecio\Parse\Scanner;
use Psecio\Parse\CallbackVisitor;
use Psecio\Parse\FileIterator;
use RuntimeException;

/**
 * The main command, scan paths for possible security issues
 */
class ScanCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('scan')
            ->setDescription('Scans paths for possible security issues')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL|InputArgument::IS_ARRAY,
                'Path to scan.',
                [getcwd()]
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED,
                'Output format (txt or xml).',
                'txt'
            )
            ->addOption(
                'include-tests',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma separated list of tests to include in the test suite.',
                ''
            )
            ->addOption(
                'exclude-tests',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma separated list of tests to exclude from the test suite.',
                ''
            )
            ->setHelp(
                "Scan paths for possible security issues:\n\n  <info>%command.full_name% /path/to/src</info>\n"
            );
    }

    /**
     * Execute the "scan" command
     *
     * @param  InputInterface   $input Input object
     * @param  OutputInterface  $output Output object
     * @throws RuntimeException If output format is not valid
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dispatcher = new EventDispatcher;
        $exitCode = new ExitCodeCatcher;
        $dispatcher->addSubscriber($exitCode);

        switch (strtolower($input->getOption('format'))) {
            case 'txt':
                if ($output->isVeryVerbose()) {
                    $dispatcher->addSubscriber(new ConsoleDebug($output));
                } elseif ($output->isVerbose()) {
                    $dispatcher->addSubscriber(new ConsoleVerbose($output));
                } else {
                    $dispatcher->addSubscriber(new ConsoleStandard($output));
                }
                $dispatcher->addSubscriber(new ConsoleReport($output));
                break;
            case 'xml':
                $dispatcher->addSubscriber(new Xml($output));
                break;
            default:
                throw new RuntimeException("Unknown output format '{$input->getOption('format')}'");
        }

        $ruleFactory = new RuleFactory(
            array_filter(explode(',', $input->getOption('include-tests'))),
            array_filter(explode(',', $input->getOption('exclude-tests')))
        );

        $scanner = new Scanner($dispatcher, new CallbackVisitor($ruleFactory->createRuleCollection()));

        $scanner->scan(
            new FileIterator($input->getArgument('path'))
        );

        return $exitCode->getExitCode();
    }
}
