<?php

namespace App\Shared\Http\Controllers;

use Illuminate\Http\Response;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

class MetricsController extends Controller
{
    public function __invoke(CollectorRegistry $registry): Response
    {
        $renderer = new RenderTextFormat;

        return response(
            $renderer->render($registry->getMetricFamilySamples()),
            200,
            ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8'],
        );
    }
}
