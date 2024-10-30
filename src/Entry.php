<?php

namespace Curatorium\EasyConfig;

class Entry
{
    public const ENTRY_DESCRIPTOR = '#^(?<name>[^:]++)?:(?<type>[^@]++)(?:@(?<env>.++))?$#x';
    public const PARAM_DESCRIPTOR = '#^(?<name>[^@]++)(?:@(?<env>.++))?$#x';

    /** @var array<int|string, scalar> */
    public array $tags = [];

    /** @var array<string, array<string, mixed>> The format is [env => [param => value]] */
    public array $params = [];

    /**
     * @param string               $descriptor an entry name with a required type specifier and an optional environment
     *                                         specifier (ex.: `entry-abc:type-xyz@prod-1`).
     * @param array<string, mixed> $params     a key/value map of parameters
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(private Entries $entries, string $descriptor, array $params = [])
    {
        $descriptor = self::parse($descriptor, static::ENTRY_DESCRIPTOR);
        $parts = explode('.', $descriptor->name, 3);

        $this->tags['type'] = $descriptor->type;
        $name = array_pop($parts);
        $module = array_shift($parts) ?? null;
        $feature = $parts[0] ?? null;

        $this->tags['module'] = $module;
        $this->tags['feature'] = $feature;
        $this->tags['name'] = $name;

        if (!isset($this->params[$descriptor->env])) {
            $this->params[$descriptor->env] = [];
        }
        foreach ($params as $param => $value) {
            $this->withParam($param, $descriptor->env, $value);
        }

        $this->tags['fullname'] = $this->fullname();
    }

    public function fullname(): string
    {
        $parts = array_filter([$this->tags['module'], $this->tags['feature'], $this->tags['name']]);
        $name = implode('.', $parts);

        return implode(':', [$name, $this->tags['type']]);
    }

    public function write(string $env, callable $postprocessor): void
    {
        /* Empty name implies :<type> or :Default */
        if (empty($this->tags['name'])) {
            return;
        }

        $params = $this->params($env);
        if ($params === null) {
            return;
        }

        $type = $this->tags['type'];
        $typeDefaults = [];
        if (isset($this->entries[':'.$type])) {
            $typeDefaults = $this->entries[':'.$type]?->params($env);
        }

        $defaults = [];
        if (isset($this->entries[':Default'])) {
            $defaults = $this->entries[':Default']->params($env);
        }

        $params = array_merge($defaults, $typeDefaults, $params);

        $postprocessor($type, $params + [
            'tags' => $this->tags,
            'env' => new Env($env),
            'params' => $params,
            'own' => $this->params($env),
            ':'.$type => $typeDefaults,
            ':Default' => $defaults,
        ]);
    }

    /**
     * @param string $descriptor A parameter name with an optional environment specifier (ex.: `param-abc@prod-1`).
     * @param string $env        The environment specifier of containing Entry. Must match or be a superset of the parameter's (ex.: `prod`).
     *
     * @throws \InvalidArgumentException
     */
    private function withParam(string $descriptor, string $env, mixed $value): void
    {
        $descriptor = self::parse($descriptor, static::PARAM_DESCRIPTOR);
        if (!empty($descriptor->env) and !empty($env) and !str_contains($descriptor->env, $env)) {
            $msg = sprintf(
                "Parameter '%s@%s/%s@%s' has conflicting env spec.\n",
                $this->tags['fullname'],
                $env,
                $descriptor->name,
                $descriptor->env,
            );
            throw new \InvalidArgumentException($msg);
        }

        if (str_starts_with($descriptor->name, ':')) {
            $this->tags[ltrim($descriptor->name, ':')] = $value;
        } else {
            $this->params[$descriptor->env ?: $env][$descriptor->name] = $value;
        }
    }

    /**
     * @return ?array<string, mixed>
     */
    private function params(string $env): ?array
    {
        $envBase = explode('-', $env, 2)[0];

        /* Entry is undefined for this environment spec */
        if (!isset($this->params['']) and !isset($this->params[$envBase]) and !isset($this->params[$env])) {
            return null;
        }

        return array_merge(
            $this->params[''] ?? [],
            $this->params[$envBase] ?? [],
            $this->params[$env] ?? [],
        );
    }

    /**
     * @param array<string, mixed> $defaults
     */
    private static function parse(string $subject, string $pattern, array $defaults = ['env' => '']): \stdClass
    {
        if (!preg_match($pattern, $subject, $m)) {
            return new \stdClass();
        }

        $result = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
        $result = array_merge($defaults, $result);

        return (object) $result;
    }
}
