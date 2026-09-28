<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Exceptions\InvalidConfigurationException;

/*
|--------------------------------------------------------------------------
| The message is the whole diagnosis
|--------------------------------------------------------------------------
|
| Nobody reads this exception's type to decide anything: it stops a boot or a response, and
| then a person reads it. So the message has to carry the three things that person needs —
| which key, what it holds, and where to fix it — and `describe()` is the part that names the
| value. That vocabulary is asserted here on its own, because which reader refused a value is
| incidental to it: a key that is present and unreadable has to read the same way whichever
| reader got there first.
|
| `unreadable()` is called with the readers' own values in every other test; the table below
| states the vocabulary in full, including the two values a reader never gets to pass on — the
| readers treat `null` as absent before they refuse anything, and only a value that is present
| and unreadable reaches this method.
|
*/

it('says the key is not set, and where the key comes from', function (): void {
    $message = InvalidConfigurationException::absent('response-compression.algorithm')->getMessage();

    expect($message)->toContain('response-compression.algorithm is not set.')
        ->and($message)->toContain('artisan vendor:publish --tag=response-compression-config');
});

it('names the value it found, in the terms the operator wrote it in', function (mixed $value, string $written): void {
    $message = InvalidConfigurationException::unreadable('response-compression.min_length', 'an integer', $value)->getMessage();

    expect($message)->toContain('is set to '.$written.', which is not an integer.');
})->with([
    [null, 'null'],
    [true, 'the boolean true'],
    [false, 'the boolean false'],
    [7, 'the integer 7'],
    [-1, 'the integer -1'],
    [1.5, 'the float 1.5'],
    ['4096', "the string '4096'"],
    ['', "the string ''"],
    [['br'], 'an array of 1 item(s)'],
    [new stdClass, 'an instance of stdClass'],
]);

it('points at the file to fix, and at the reason a .env value may be a string', function (): void {
    // The refusal is only half of it: the operator has a key to change, and the commonest
    // way to reach this is a value that arrived from .env, which is why the second half
    // names the record rather than repeating the rule.
    $message = InvalidConfigurationException::unreadable('response-compression.min_length', 'an integer', '4096a')->getMessage();

    expect($message)->toContain('Fix it in config/response-compression.php')
        ->and($message)->toContain('docs/env-types.md');
});
