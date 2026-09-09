<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Prometheus\CollectorRegistry;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| Read the Prometheus metric value for a specific label set so feature and
| unit tests can assert collected metrics without parsing text output.
|
*/

/**
 * @param  list<string>  $labelValues
 */
function counterValue(CollectorRegistry $registry, string $name, array $labelValues): int
{
    foreach ($registry->getMetricFamilySamples() as $family) {
        if ($family->getName() !== $name) {
            continue;
        }

        foreach ($family->getSamples() as $sample) {
            if ($sample->getLabelValues() === $labelValues) {
                return (int) $sample->getValue();
            }
        }
    }

    return 0;
}
