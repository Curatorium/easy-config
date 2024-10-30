<?php

namespace Curatorium\EasyConfig;

class Entry
{
    public const ENTRY_DESCRIPTOR = '#^(?<name>[^:]++)?:(?<type>[^@]++)(?:@(?<env>.++))?$#x';
    public const PARAM_DESCRIPTOR = '#^(?<name>[^@]++)(?:@(?<env>.++))?$#x';

    public string $type;
    public ?string $module;
    public ?string $feature;
    public string $name;
    public bool $enabled;

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
        $parts = array_reverse(explode('.', $descriptor->name, 3));

        $this->type = $params[':type'] ?? $descriptor->type;
        $this->module = $params[':module'] ?? $parts[2] ?? null;
        $this->feature = $params[':feature'] ?? $parts[1] ?? null;
        $this->name = $params[':name'] ?? $parts[0];
        $this->enabled = $params[':enabled']
            ?? (isset($params[':disabled']) ? !$params[':disabled'] : null)
            ?? true;
        unset($params[':enabled'], $params[':disabled']);

        foreach ($params as $param => $value) {
            $this->withParam($param, $descriptor->env, $value);
        }
    }

    public function name(): string
    {
        $parts = array_filter([$this->module, $this->feature, $this->name]);
        $name = implode('.', $parts);

        return implode(':', [$name, $this->type]);
    }

    public function write(string $env, callable $postprocessor): void
    {
        /* Empty name implies :<type> or :Default */
        if (empty($this->name)) {
            return;
        }

        /* Empty parameters implies it's defined for different environments */
        $params = $this->params($env);
        if (empty($params)) {
            return;
        }

        $typeDefaults = [];
        if (isset($this->entries[':'.$this->type])) {
            $typeDefaults = $this->entries[':'.$this->type]?->params($env);
        }

        $defaults = [];
        if (isset($this->entries[':Default'])) {
            $defaults = $this->entries[':Default']->params($env);
        }


        $params = array_merge($defaults, $typeDefaults, $params);

        $postprocessor($this->type, $params + [
            'ctx' => $this,
            'env' => new Env($env),
            'defaults' => $defaults,
            'typeDefaults' => $typeDefaults,
            'own' => $this->params($env),
            'params' => $params,
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
                $this->name(),
                $env,
                $descriptor->name,
                $descriptor->env,
            );
            throw new \InvalidArgumentException($msg);
        }

        $this->params[$descriptor->env ?: $env][$descriptor->name] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function params(string $env): array
    {
        $envBase = explode('-', $env, 2)[0];

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
