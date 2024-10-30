<?php

namespace Curatorium\EasyConfig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class App extends Command
{
    public function getName(): ?string
    {
        return 'easy-config';
    }

    /**
     * Load defaults from .easy-config.
     */
    protected function configure(): void
    {
        if (file_exists('./.easy-config')) {
            $defaults = yaml_parse_file('./.easy-config');
        }

        $this
            ->addArgument('env', InputArgument::OPTIONAL, 'Environment specifier to resolving parameters.', $defaults['env'] ?? null)

            ->addArgument('in-files', InputArgument::IS_ARRAY, 'Files to process.', $defaults['in-files'] ?? ['php://stdin'])
            ->addOption('out-files', 'o', InputOption::VALUE_OPTIONAL, 'Pattern for where to write output.', $defaults['out-files'] ?? 'php://stdout')

            ->addOption('templates', 't', InputOption::VALUE_OPTIONAL, 'Templates directory.', $defaults['templates'] ?? 'tpl/')

            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL, 'A YAML string filter to apply to the entries.', $defaults['filter'] ?? [])
            ->addOption('resolve', 'r', InputOption::VALUE_NONE, 'Output configuration as YAML.')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $opts = $input->getArguments() + $input->getOptions();
        if (is_string($opts['filter'])) {
            $opts['filter'] = yaml_parse($opts['filter']);
        }

        $writer = new Writer($opts['templates']);
        $preprocessor = fn ($source) => $writer->renderSource($source, ['env' => new Env($opts['env'])]);
        $postprocessor = fn ($type, $params) => $writer->renderType($type, $params, $opts['out-files'], $opts['resolve']);

        Entries::read($opts['in-files'], $opts['filter'], $preprocessor)->write($opts['env'], $postprocessor);

        return self::SUCCESS;
    }
}
