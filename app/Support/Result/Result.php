<?php

namespace App\Support\Result;

use LogicException;

final readonly class Result
{
    private function __construct(
        private readonly mixed $value = null,
        private readonly ?ResultError $error = null,
    ) {}

    public static function ok(mixed $value = null): self
    {
        return new self(value: $value);
    }

    public static function err(ResultError $error): self
    {
        return new self(error: $error);
    }

    public function isOk(): bool
    {
        return $this->error === null;
    }

    public function isErr(): bool
    {
        return $this->error !== null;
    }

    public function unwrap(): mixed
    {
        if ($this->isErr()) {
            throw new LogicException('Cannot unwrap an error result.');
        }

        return $this->value;
    }

    public function error(): ResultError
    {
        if ($this->isOk()) {
            throw new LogicException('Cannot retrieve the error of an ok result.');
        }

        return $this->error;
    }

    public function getOr(mixed $default): mixed
    {
        return $this->isOk() ? $this->value : $default;
    }

    public function map(callable $ok): self
    {
        return $this->isOk() ? self::ok($ok($this->value)) : $this;
    }

    public function mapError(callable $err): self
    {
        return $this->isErr() ? self::err($err($this->error)) : $this;
    }

    public function match(callable $ok, callable $err): mixed
    {
        return $this->isOk() ? $ok($this->value) : $err($this->error);
    }
}
