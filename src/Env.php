<?php

namespace Curatorium\EasyConfig;

class Env
{
    public string $base;
    public string $suffix = '';

    public function __construct(string $env) {
        [$this->base, $this->suffix] = explode('-', $env, 2);
    }

    public function __toString(): string
    {
        return $this->base . '-' . $this->suffix;
    }
}
