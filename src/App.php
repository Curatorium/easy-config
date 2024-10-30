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
            ->addArgument('env', InputArgument::REQUIRED, 'Environment specifier to resolving parameters.')

            ->addArgument('in-files', InputArgument::IS_ARRAY, 'Files to process.', $defaults['in-files'] ?? ['php://stdin'])
            ->addOption('out-files', 'o', InputOption::VALUE_OPTIONAL, 'Pattern for where to write output.', $defaults['out-files'] ?? 'php://stdout')

            ->addOption('templates', 't', InputOption::VALUE_OPTIONAL, 'Templates directory.', $defaults['templates'] ?? 'tpl/')
            ->addOption('template-extension', 'x', InputOption::VALUE_OPTIONAL, 'Templates extension.', $defaults['template-extension'] ?? 'twig')

            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL, 'A filter to apply to the entry names.', $defaults['filter'] ?? ['enabled' => true])
            ->addOption('resolve', 'r', InputOption::VALUE_NONE, 'Output configuration as YAML.')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $opts = $input->getArguments() + $input->getOptions();
        if (is_string($opts['filter'])) {
            $opts['filter'] = array_merge(['enabled' => true], yaml_parse($opts['filter']));
        }

        $writer = new Writer([$opts['templates']], $opts['template-extension']);
        $preprocessor = fn ($source) => $writer->renderSource($source, ['env' => $opts['env']]);
        $postprocessor = fn ($template, $params) => $writer->renderTemplate($template, $params, $opts['out-files'], $opts['resolve']);

        Entries::read($opts['in-files'], $opts['filter'], $preprocessor)->write($opts['env'], $postprocessor);

        return self::SUCCESS;
    }
}
