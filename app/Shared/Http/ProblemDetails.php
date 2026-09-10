<?php

namespace App\Shared\Http;

use Illuminate\Http\JsonResponse;

final readonly class ProblemDetails
{
    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, mixed>  $extensions
     */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly int $status,
        public readonly string $detail,
        public readonly string $instance,
        public readonly string $code,
        public readonly ?string $traceId = null,
        public readonly array $errors = [],
        public readonly array $extensions = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
            'instance' => $this->instance,
            'code' => $this->code,
        ];

        if ($this->traceId !== null) {
            $payload['trace_id'] = $this->traceId;
        }

        if ($this->errors !== []) {
            $payload['errors'] = $this->errors;
        }

        return array_merge($payload, $this->extensions);
    }

    public function toResponse(): JsonResponse
    {
        return response()->json(
            $this->toArray(),
            $this->status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
