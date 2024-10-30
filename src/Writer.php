<?php

namespace Curatorium\EasyConfig;

use Twig\Environment as Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\DebugExtension;
use Twig\Lexer;
use Twig\Loader\FilesystemLoader as Loader;
use Twig\TwigFilter;

class Writer
{
    private Twig $twig;

    /**
     * @param string[] $templates
     */
    public function __construct(
        array $templates,
        private string $ext,
    ) {
        $twig = new Twig(new Loader(array_merge([__DIR__.'/../tpl/'], $templates)), [
            'autoescape' => false,
            'debug' => true,
            'cache' => false,
            'strict_variables' => true,
        ]);
        $twig->addExtension(new DebugExtension());
        $twig->setLexer(new Lexer($twig, ['tag_variable' => ['${', '}']]));
        $twig->addFilter(new TwigFilter('json', fn ($v) => json_encode($v, JSON_UNESCAPED_SLASHES)));
        $twig->addFilter(new TwigFilter('yaml', fn ($v) => trim(yaml_emit($v), "-\n.")));
        $twig->addFilter(new TwigFilter('b64', fn ($v) => base64_encode($v)));

        $this->twig = $twig;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function renderSource(string $source, array $params): string
    {
        return $this->twig->createTemplate($source)->render($params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function renderTemplate(string $template, array $params, string $outFiles, bool $resolve = false): void
    {
        if ($resolve) {
            $this->renderTemplate('ToYAML.twig', $params, 'php://stdout');

            return;
        }

        if (!str_contains($template, '.')) {
            $template .= '.'.$this->ext;
        }

        try {
            $output = $this->twig->load($template)->render($params);
            $outputFile = $this->renderSource($outFiles, $params);
        } catch (LoaderError $e) {
            throw $e;
        } catch (SyntaxError $e) {
            throw $e;
        } catch (RuntimeError $e) {
            $e->appendMessage("\n\n".yaml_emit([
                'ctx' => [
                    'module' => $params['ctx']->name,
                    'feature' => $params['ctx']->feature,
                    'name' => $params['ctx']->name,
                    'enabled' => $params['ctx']->enabled,
                ],
                'own' => $params['own'],
                'typeDefaults' => $params['typeDefaults'],
            ]));
            throw $e;
        }

        if (!str_starts_with($outputFile, 'php://') and !is_dir(dirname($outputFile))) {
            mkdir(dirname($outputFile), 0774, true);
        }
        file_put_contents($outputFile, $output, FILE_APPEND);
    }
}
