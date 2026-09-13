<?php

/**
 * Business modules may request authentication through IdentityAccess contracts,
 * but must never implement credential checks or token lifecycle themselves.
 * Pest's arch expectations cannot detect method calls, so this scans the source
 * of each business module for the forbidden patterns.
 *
 * The scan intentionally avoids the Laravel container/helpers so it can run
 * inside the Arch test suite without booting the application.
 */
$businessModules = ['Customer', 'Geography', 'BankDirectory', 'Service'];

$forbiddenPatterns = [
    'Hash::check(',
    'Hash::make(',
    'createToken(',
    'tokens()->delete(',
    'Auth::attempt(',
];

it('keeps credential and token implementation out of business modules', function () use ($businessModules, $forbiddenPatterns) {
    $basePath = dirname(__DIR__, 2).'/app/Modules';
    $violations = [];

    foreach ($businessModules as $module) {
        $directory = $basePath.'/'.$module;

        if (! is_dir($directory)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Tests'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach ($forbiddenPatterns as $pattern) {
                if (str_contains($contents, $pattern)) {
                    $violations[] = str_replace($basePath.'/', '', $file->getPathname()).' -> '.$pattern;
                }
            }
        }
    }

    expect($violations)->toBe([]);
});
