<?php

use Illuminate\Support\Facades\Route;
use Prometheus\CollectorRegistry;

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::get('/log', fn () => 'ok')->name('api.v1.log');
        Route::get('/forbidden', fn () => abort(403));
        Route::get('/broken', fn () => throw new RuntimeException('boom'));
    });

    app(CollectorRegistry::class)->wipeStorage();
    config(['app.debug' => false]);
});

/**
 * @return list<int>
 */
function histogramCounts(CollectorRegistry $registry, string $name, array $labelValues): array
{
    $counts = [];

    foreach ($registry->getMetricFamilySamples() as $family) {
        if ($family->getName() !== $name) {
            continue;
        }

        foreach ($family->getSamples() as $sample) {
            $labels = $sample->getLabelValues();
            $value = (int) $sample->getValue();

            if ($labels[0] === $labelValues[0] && $labels[1] === $labelValues[1]) {
                $counts[] = $value;
            }
        }
    }

    return $counts;
}

it('records request counts grouped by method, route, and status', function () {
    $registry = app(CollectorRegistry::class);

    $this->getJson('/api/v1/log')->assertOk();
    $this->getJson('/api/v1/forbidden')->assertStatus(403);

    expect(counterValue($registry, 'http_requests_total', ['GET', 'api.v1.log', '200']))->toBe(1)
        ->and(counterValue($registry, 'http_requests_total', ['GET', 'api/v1/forbidden', '403']))->toBe(1);
});

it('does not count a 4xx response as a server error', function () {
    $registry = app(CollectorRegistry::class);

    $this->getJson('/api/v1/forbidden')->assertStatus(403);

    expect(counterValue($registry, 'http_errors_total', ['GET', 'api/v1/forbidden', '403']))->toBe(0);
});

it('increments the error metric on a 5xx response', function () {
    $registry = app(CollectorRegistry::class);

    $this->getJson('/api/v1/broken')->assertStatus(500);

    expect(counterValue($registry, 'http_errors_total', ['GET', 'api/v1/broken', '500']))->toBe(1);
});

it('records request duration as a histogram', function () {
    $registry = app(CollectorRegistry::class);

    $this->getJson('/api/v1/log')->assertOk();

    $counts = histogramCounts($registry, 'http_request_duration_seconds', ['GET', 'api.v1.log']);

    expect($counts)->not->toBeEmpty()
        ->and($counts)->toContain(1);
});

it('keeps metric labels bounded without trace or user identifiers', function () {
    $registry = app(CollectorRegistry::class);

    $this->withHeader('X-Trace-Id', 'user-supplied-abc')
        ->getJson('/api/v1/log')
        ->assertOk();

    foreach ($registry->getMetricFamilySamples() as $family) {
        expect($family->getLabelNames())->not->toContain('trace_id')
            ->and($family->getLabelNames())->not->toContain('user_id')
            ->and($family->getLabelNames())->not->toContain('ip');

        foreach ($family->getSamples() as $sample) {
            foreach ($sample->getLabelValues() as $value) {
                expect($value)->not->toContain('user-supplied-abc');
            }
        }
    }
});
