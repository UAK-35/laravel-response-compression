<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ConfigKeys;

/*
|--------------------------------------------------------------------------
| The config file and the code that reads it
|--------------------------------------------------------------------------
|
| Two of this package's defects were a key and a reader disagreeing, and each was invisible
| from the side it was on: BrotliEncoder read `response-compression.brotli.level`, which the
| config does not publish (the section is `br`), so the level was always the default — and
| `enable_logging` was published while nothing read it at all. It is wired to the middleware's
| debug logging now, which is why `ConfigKeys::UNWIRED` is empty.
|
| So the two sets are read and compared in both directions. A typo in a key is one failing
| assertion instead of a setting that quietly does nothing.
|
*/

/**
 * @guards-index guard the config-key test
 *
 * a key read that the config does not publish, and a key published that nothing reads
 */
beforeEach(function (): void {
    $this->published = ConfigKeys::published(packageRoot().'/config/response-compression.php');
    // One record per read rather than per key: `Config::validate()` reads everything the
    // middleware and the encoders read, so a key read twice where only one read is right is
    // the case this has to see. See ConfigKeys::read().
    $this->read = array_column(ConfigKeys::read(packageRoot().'/src')['reads'], 'path');
});

it('reads only keys the config file publishes', function (): void {
    // No reader is exempt. A lookup that finds nothing is a setting that does nothing, and
    // that is as true of a reader which substitutes a default as of one which refuses the
    // request — the brotli level was read by `intInRange` and had been wrong for so long
    // because the default it substituted looked like a working value.
    //
    // The walk and the comparison live in `ConfigKeys::unknown()` rather than here, so the
    // mutation harness can ask the same call with a reader of an unpublished key planted in a copy
    // of the tree. A key assembled at runtime names one setting across every algorithm section, so
    // it is published when any section has it, and the reading carries that half too.
    expect($this->published)->not->toBeEmpty()
        ->and($this->read)->not->toBeEmpty()
        ->and(ConfigKeys::unknown(packageRoot()))->toBe([]);
});

it('publishes no key that nothing reads', function (): void {
    // The other direction, as one call. A published key is a promise to whoever sets it, and a key
    // nothing reads breaks that promise silently — so the only way for one to exist is to be in
    // ConfigKeys::UNWIRED, and the test after this one holds that entry to its record.
    expect(ConfigKeys::unread(packageRoot()))->toBe([]);
});

it('names the record that lets an unread key exist', function (): void {
    // An allow-list with no evidence behind it is where a defect goes to be forgotten, so an
    // entry in it has to be named by the document it points at — and it has to be a key that
    // nothing reads, or the exemption outlives the thing it was granted for. `enable_logging`
    // is read by the middleware's debug logging now (docs/unwired-config.md), so the list is
    // empty: both halves are collected and compared as sets rather than asserted inside the
    // loop, because a loop over an empty list asserts nothing at all, and a test that asserts
    // nothing is reported as risky rather than as green.
    $unanchored = [];
    $stale = [];

    foreach (ConfigKeys::UNWIRED as $path => $record) {
        $file = packageRoot().'/'.$record;

        // `is_file()` first, and short-circuited: a missing record has to be reported, not turned
        // into a warning by the read that would follow it.
        $named = is_file($file) && str_contains((string) file_get_contents($file), $path);

        if (! $named) {
            $unanchored[] = sprintf('%s is exempt with no record naming it in %s', $path, $record);
        }

        if (in_array($path, $this->read, true)) {
            $stale[] = sprintf('%s is exempt from being read, and is read', $path);
        }
    }

    expect($unanchored)->toBe([])
        ->and($stale)->toBe([]);
});

it('reads the source with a reader the package has', function (): void {
    // The walk lives in `ConfigKeys::unknownReaders()` rather than here, so the test and the
    // mutation harness ask the same call: a reader renamed in the class is the break it has to
    // name. The scan reads the *name* of each reader, so a reader this class does not know would
    // be a key read invisibly — invisible in both directions at once. Reading the class itself
    // keeps the list complete: a new reader, or one renamed, fails here first.
    expect(ConfigKeys::unknownReaders(packageRoot()))->toBe([])
        ->and($this->read)->not->toBeEmpty();
});
