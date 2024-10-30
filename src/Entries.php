<?php

namespace Curatorium\EasyConfig;

/**
 * @template-implements \ArrayObject<string, Entry>
 */
class Entries extends \ArrayObject
{
    /** @var array{entry: string, enabled: bool} */
    private array $filter;

    /**
     * Treat $files as a list of as glob patterns.
     * Read each $files and preprocess it (twig template with ${env} param).
     * Parse YAML/JSON from $files.
     * If eJSON then use shopify/ejson to decrypt.
     * if eYAML then use `eyaml` wrapper to convert to eJSON and then decrypt.
     * Merge entries from all files into a single collection.
     *
     * @param string[]                            $globs
     * @param array{entry: string, enabled: bool} $filter
     */
    public static function read(array $globs, array $filter, callable $preprocessor): self
    {
        $files = [];
        foreach ($globs as $glob) {
            $files[] = glob($glob);
        }
        $files = array_merge(...$files);

        $data = [];
        foreach ($files as $file) {
            $raw = match (pathinfo($file, PATHINFO_EXTENSION)) {
                'ejson' => shell_exec('ejson decrypt '.$file),
                'eyml', 'eyaml' => shell_exec('eyaml decrypt '.$file),
                default => file_get_contents($file),
            };

            $raw = $preprocessor($raw);
            $data[$file] = (array) yaml_parse($raw);
            unset($data[$file]['_public_key']);
        }
        $entries = array_merge_recursive(...array_values($data));

        $self = new self();
        foreach ($entries as $name => $params) {
            /* @phpstan-ignore-next-line */
            $self[$name] = (array) $params;
        }
        $self->filter = $filter;

        return $self;
    }

    /**
     * @param string                     $key
     * @param array<string, mixed>|Entry $value
     */
    public function offsetSet($key, $value): void
    {
        if (!$value instanceof Entry) {
            $value = new Entry($this, $key, (array) $value);
            $key = $value->name();
        }

        if (isset($this[$key])) {
            $value->params = array_merge_recursive($this[$key]->params, $value->params);
        }

        parent::offsetSet($value->name(), $value);
    }

    public function write(string $env, callable $postprocessor): void
    {
        foreach ($this as $entry) {
            $filterPrefix = empty($this->filter['entry']) or str_starts_with($entry->name(), $this->filter['entry']);
            $matchStatus = $entry->enabled === $this->filter['enabled'];

            if ($matchStatus and $filterPrefix) {
                $entry->write($env, $postprocessor);
            }
        }
    }
}
