<?php

declare(strict_types=1);

/* @guards-index test */

use Uak35\ResponseCompression\Support\AcceptEncoding;

/*
|--------------------------------------------------------------------------
| The Accept-Encoding header, read as what the client asked for
|--------------------------------------------------------------------------
|
| Two readings, and the second is the one the middleware acts on: the header as a quality per
| coding, and whether the client asked for the body as it is. The cases that carry the weight
| are the ones `Request::getEncodings()` cannot express - a quality, a coding written in
| capitals, and `identity` refused rather than requested - because those are the ones that
| were indistinguishable from something else before this existed.
|
| The reading is pinned as a whole array rather than key by key, so a coding that arrives
| twice, or a quality that lands under the wrong coding, fails here rather than passing on
| the one entry the assertion happened to look at.
|
*/

it('reads the quality each coding is given, or the default when it is given none', function (?string $header, array $expected): void {
    expect(AcceptEncoding::preferences($header))->toBe($expected);
})->with([
    'no header at all' => [null, []],
    'an empty header' => ['', []],
    'one coding' => ['gzip', ['gzip' => 1.0]],
    'a coding and its quality' => ['gzip;q=0.5', ['gzip' => 0.5]],
    'a space after the separator' => ['gzip; q=0.5', ['gzip' => 0.5]],
    'whitespace around the coding' => ['  gzip  ', ['gzip' => 1.0]],
    'the parameter name in capitals' => ['gzip;Q=0.4', ['gzip' => 0.4]],
    'two codings' => ['gzip, identity', ['gzip' => 1.0, 'identity' => 1.0]],
    'two codings and two qualities' => ['gzip;q=1.0, identity;q=0.5', ['gzip' => 1.0, 'identity' => 0.5]],
    'the wildcard' => ['*', ['*' => 1.0]],
    'a coding in capitals' => ['IDENTITY', ['identity' => 1.0]],
    'a coding written twice' => ['gzip, gzip;q=0.2', ['gzip' => 0.2]],
    'an entry with no coding in it' => [', gzip', ['gzip' => 1.0]],
    'a parameter that is not a quality' => ['gzip;level=9', ['gzip' => 1.0]],
    'a quality that will not parse' => ['gzip;q=nonsense', ['gzip' => 1.0]],
    'a quality above the range' => ['gzip;q=2', ['gzip' => 1.0]],
    'a quality below the range' => ['gzip;q=-1', ['gzip' => 0.0]],
]);

it('asks for the body as it is only when the header actually names identity', function (?string $header, string $coding, bool $expected): void {
    expect(AcceptEncoding::prefersIdentity($header, $coding))->toBe($expected);
})->with([
    'identity alone' => ['identity', 'gzip', true],
    'identity beside an encoding it ties with' => ['gzip, identity', 'gzip', true],
    'identity ranked above the encoding' => ['gzip;q=0.5, identity', 'gzip', true],
    'identity ranked level with the encoding' => ['gzip;q=0.9, identity;q=0.9', 'gzip', true],
    'identity in capitals' => ['IDENTITY', 'gzip', true],
    'identity beside the wildcard' => ['*, identity', 'gzip', true],
    'the coding named nowhere at all' => ['identity, gzip', 'br', true],
    'identity ranked below the encoding' => ['gzip;q=1.0, identity;q=0.5', 'gzip', false],
    'identity refused rather than requested' => ['identity;q=0, gzip', 'gzip', false],
    'identity refused on its own' => ['identity;q=0', 'gzip', false],
    'identity never mentioned' => ['gzip', 'gzip', false],
    'the wildcard alone' => ['*', 'gzip', false],
    'no header at all' => [null, 'gzip', false],
]);
