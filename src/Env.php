<?php

namespace Curatorium\EasyConfig;

class Env
{
    public string $base;
    public string $suffix = '';

    public function __construct(string $env) {
        $parts = explode('-', $env, 2);
        $this->base = $parts[0];
        $this->suffix = $parts[1] ?? '';
    }

    public function __toString(): string
    {
        return implode('-', [$this->base, $this->suffix]);
    }
}
