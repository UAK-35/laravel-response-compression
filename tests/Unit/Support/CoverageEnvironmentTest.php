<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\CoverageEnvironment;

/*
|--------------------------------------------------------------------------
| The environment a floor is measured in
|--------------------------------------------------------------------------
|
| The header `bin/coverage.php` opens with, and the decision behind it. What is asserted here is
| the whole reason that header exists: a floor of 0.0% on a machine with no regression in it is
| either the wrong interpreter or a driver that cannot count a line, and either one reads as a code
| change when it is not named.
|
| The observations are handed in rather than read, because two of these branches cannot be produced
| on a runner that installs PCOV and nothing else: a PCOV loaded and switched off, and an Xdebug
| left in `develop`. Both are a driver that is present and cannot count — the reading that a
| careless "is a driver loaded?" gets wrong, and the one that would make a report say the machine is
| fine while every file measures 0.0%.
|
*/

/**
 * An environment as observed, with every fact given rather than read, so no assertion here turns on
 * the machine the suite happens to be running on.
 */
function coverageEnvironment(
    array $extensions = [],
    string $pcovEnabled = '1',
    string $xdebugMode = '',
    string $composer = '',
): CoverageEnvironment {
    return CoverageEnvironment::detect($extensions, $pcovEnabled, $xdebugMode, $composer);
}

it('names PCOV as the driver when it is loaded and switched on', function (): void {
    $pcov = coverageEnvironment(['pcov' => '1.0.12']);

    expect($pcov->driver)->toBe('PCOV 1.0.12')
        // Nothing is wrong, so there is nothing to explain and nothing to fix: a header that carried
        // a remedy for an environment that is already able to measure would be advice to undo it.
        ->and($pcov->why)->toBe('')
        ->and($pcov->hint)->toBe('');
});

it('counts Xdebug as a driver only when its mode has coverage in it', function (): void {
    $xdebug = ['xdebug' => '3.4.1'];

    expect(coverageEnvironment($xdebug, xdebugMode: 'coverage')->driver)
        ->toBe('Xdebug 3.4.1 (xdebug.mode=coverage)')

        // A mode list, which is how Xdebug 3 spells several at once.
        ->and(coverageEnvironment($xdebug, xdebugMode: 'develop,coverage')->driver)
        ->toBe('Xdebug 3.4.1 (xdebug.mode=develop,coverage)')

        // And the one that matters: a driver loaded for step debugging counts nothing, so reporting
        // it as a driver beside a floor of 0.0% is the sentence that sends a reader to the wrong file.
        ->and(coverageEnvironment($xdebug, xdebugMode: 'develop')->driver)->toBe('')
        ->and(coverageEnvironment($xdebug, xdebugMode: 'develop')->why)
        ->toContain('xdebug.mode=develop, which has no coverage in it');
});

it('refuses a PCOV that is loaded and switched off, however off is spelled', function (): void {
    $off = coverageEnvironment(['pcov' => '1.0.12'], pcovEnabled: '0');

    expect($off->driver)->toBe('')
        ->and($off->why)->toContain('pcov 1.0.12 is loaded but pcov.enabled is off')
        ->and($off->hint)->toContain('pcov.enabled=1')

        // `ini_get` answers '' for the spellings it does not answer '0' for, so a check that only
        // compared against a zero would call an ini file's `Off` a working driver.
        ->and(coverageEnvironment(['pcov' => '1.0.12'], pcovEnabled: '')->driver)->toBe('')
        ->and(coverageEnvironment(['pcov' => '1.0.12'], pcovEnabled: 'Off')->driver)->toBe('')
        ->and(coverageEnvironment(['pcov' => '1.0.12'], pcovEnabled: 'On')->driver)->toBe('PCOV 1.0.12');
});

it('falls back to a driver that can count when the first one cannot', function (): void {
    // Both installed, PCOV off: this is a machine whose floor is measured by Xdebug, and reporting
    // "no driver" because the extension that is present is switched off would be wrong in the other
    // direction — the run would have measured everything.
    $both = coverageEnvironment(
        ['pcov' => '1.0.12', 'xdebug' => '3.4.1'],
        pcovEnabled: '0',
        xdebugMode: 'coverage',
    );

    expect($both->driver)->toBe('Xdebug 3.4.1 (xdebug.mode=coverage)')
        ->and($both->why)->toBe('');
});

it('says which extensions are there when nothing can count, and what would change it', function (): void {
    $none = coverageEnvironment();

    expect($none->driver)->toBe('')
        ->and($none->why)->toBe('pcov is not loaded; xdebug is not loaded')
        ->and($none->hint)->toContain('install PCOV')

        // The fix named is the one already on the machine: telling someone to install the driver
        // they have is advice they cannot act on.
        ->and(coverageEnvironment(['xdebug' => '3.4.1'], xdebugMode: 'off')->hint)
        ->toContain('xdebug.mode');
});

it('names the interpreter, who started it and the driver, in the header', function (): void {
    $binary = str_replace('\\', '/', PHP_BINARY);
    $composed = coverageEnvironment(['pcov' => '1.0.12'], composer: '/tools/composer.phar');
    $direct = coverageEnvironment(['pcov' => '1.0.12']);

    expect($composed->describe())
        ->toContain('Coverage floor, measured under:')
        ->toContain(PHP_VERSION)
        ->toContain($binary)
        ->toContain('coverage driver')
        ->toContain('PCOV 1.0.12')

        // Which PHP Composer is running under is the question the header opens with, and it is only
        // answerable when a Composer script is what started this: `@php` is Composer's own binary,
        // where a run typed into a shell is the PHP whoever typed it chose.
        ->toContain('the PHP above is the one it runs under')
        ->toContain('COMPOSER_BINARY=/tools/composer.phar')
        ->and($direct->describe())->toContain('a direct run')

        // The instruction is a row of its own, and only on the run that has one.
        ->and($composed->describe())->not->toContain('to fix it')
        ->and(coverageEnvironment()->describe())->toContain('to fix it');
});

it('repeats the interpreter at the foot of a failure', function (): void {
    $binary = str_replace('\\', '/', PHP_BINARY);

    expect(coverageEnvironment(['pcov' => '1.0.12'])->summary())
        ->toBe('PHP '.PHP_VERSION.' ('.PHP_SAPI.') at '.$binary.', coverage by PCOV 1.0.12.')

        // A floor that moved is the code or the PHP, and the reader meeting it at the bottom of two
        // minutes of output is told which PHP that was — including when the answer is that there was
        // no driver to count with.
        ->and(coverageEnvironment()->summary())->toContain('with no coverage driver in it.');
});
