<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ConfigDoc;

/*
|--------------------------------------------------------------------------
| The README's copy of the config file
|--------------------------------------------------------------------------
|
| The README publishes the whole config, and every default in it is a contract: an
| operator reads `min_length` there and sizes an endpoint against it, and reads
| `algorithm` there and decides whether to install brotli. Until this test existed,
| nothing noticed when the two disagreed — the README advertised `algorithm` as `gzip`
| and `min_length` as `1024` while the file shipped `br` and `2048`.
|
| Both sides are read by the same reader, so this compares the README with the config
| file rather than comparing a test's own retyped copy with either.
|
*/

it('documents every config key with the value the config file ships', function (): void {
    $shipped = ConfigDoc::shipped(packageRoot().'/config/response-compression.php');
    $documented = ConfigDoc::documented(packageRoot().'/README.md', '## Config');

    // A reader that found nothing would agree with a README that documents nothing,
    // so the two sides are checked for substance before they are compared.
    expect($shipped['leaves'])->not->toBeEmpty()
        ->and($documented['leaves'])->not->toBeEmpty()
        ->and($documented['leaves'])->toBe($shipped['leaves']);
});

it('documents every config list with the items the config file ships', function (): void {
    $shipped = ConfigDoc::shipped(packageRoot().'/config/response-compression.php');
    $documented = ConfigDoc::documented(packageRoot().'/README.md', '## Config');

    expect($shipped['lists'])->not->toBeEmpty()
        ->and($documented['lists'])->not->toBeEmpty()
        ->and($documented['lists'])->toBe($shipped['lists']);
});
