<?php

namespace Curatorium\EasyConfig;

/**
 * @template-implements \ArrayObject<string, Entry>
 */
class Entries extends \ArrayObject
{
    /** @var array<int|string, scalar> */
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
     * @param array<int|string, scalar> $filter
     */
    public static function read(array $globs, array $filter, callable $preprocessor): self
    {
        $files = [];
        foreach ($globs as $glob) {
            $files[] = glob($glob);
        }
        $files = array_merge(in_array('php://stdin', $globs) ? ['php://stdin'] : [], ...$files);

        $data = [];
        foreach ($files as $file) {
            $raw = match (pathinfo($file, PATHINFO_EXTENSION)) {
                'ejson' => shell_exec('ejson decrypt '.$file),
                'eyml', 'eyaml' => shell_exec('eyaml decrypt '.$file),
                default => file_get_contents($file),
            };

            $raw = $preprocessor($raw);
            $callbacks = ['!map' => fn($v) => (object)$v];
            try {
                $data[$file] = (array)yaml_parse($raw, 0, $_, $callbacks);
            } catch (\Throwable $e) {
                throw new \RuntimeException("Failed to parse $file: ".$e->getMessage());
            }
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
            $key = $value->fullname();
        }

        if (isset($this[$key])) {
            $value->params = array_merge_recursive($this[$key]->params, $value->params);
        }

        parent::offsetSet($value->fullname(), $value);
    }

    public function write(string $env, callable $postprocessor): void
    {
        /** @var Entry $entry */
        foreach ($this as $entry) {
            if (!$this->checkFilter($entry)) {
                continue;
            }

            $entry->write($env, $postprocessor);
        }
    }

    public function checkFilter(Entry $entry): bool
    {
        /* Key/value tags (values that have string keys) */
        $filters = array_filter($this->filter, 'is_string', ARRAY_FILTER_USE_KEY);
        foreach ($filters as $key => $value) {
            if (!isset($entry->tags[$key]) or $entry->tags[$key] !== $value) {
                return false;
            }
        }

        /* Simple tags (indexed values) */
        $filters = array_filter($this->filter, 'is_int', ARRAY_FILTER_USE_KEY);
        foreach ($filters as $value) {
            if (!in_array($value, $entry->tags, true)) {
                return false;
            }
        }

        return true;
    }
}
