<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ConfigClaims;
use Uak35\ResponseCompression\Tests\Support\ConfigDoc;
use Uak35\ResponseCompression\Tests\Support\ConfigKeys;

/*
|--------------------------------------------------------------------------
| What the documents say about the keys
|--------------------------------------------------------------------------
|
| The keys the config publishes and the keys the source reads are compared in
| ConfigKeysTest. This is the third place a key is described, and the one that comparison
| cannot see: the prose. A docblock or a record saying a key is unread is a claim about the
| code, so it can be held to the code — and this package has already shipped the drift once.
| `enable_logging` was published, read by nothing, and its docblock in the config file and in
| the README both said so; the wiring made the comment wrong and nothing noticed, because the
| claim and the reader are each correct about themselves. The disagreement is only visible
| when the two are put side by side, which is what this does — so it is found by the suite
| rather than by someone reading the file and grepping for the key to be sure.
|
| A claim is read in the two places it is written: the docblock beside a key, in the config
| file and in the README's copy of it, and a sentence in the README or a design record, where
| the key names itself in a code span. Neither is a claim when it accounts for the key having
| been wired — "read by nobody, until the middleware was wired to it" is the fix, not the
| defect — and the reader behind this is exercised on that distinction below, because its
| green tick here is a scan that found no claims at all.
|
*/

beforeEach(function (): void {
    $this->root = str_replace('\\', '/', packageRoot());
    $this->published = ConfigKeys::published($this->root.'/config/response-compression.php');
    $this->claims = ConfigClaims::all($this->root, $this->published);
    $this->reads = ConfigKeys::read($this->root.'/src');
});

it('claims nothing is unread that the source reads', function (): void {
    // A reader that found no keys would report no claims and agree with documents that say
    // nothing, so the docblock half is asserted for substance before it is believed: every
    // published key has a docblock slot, empty or not. That is what makes the scan below a
    // scan of this package's keys rather than of whatever the reader happened to see.
    $docblocks = ConfigDoc::shipped($this->root.'/config/response-compression.php')['docblocks'];

    $missing = array_values(array_diff($this->published, array_keys($docblocks)));

    // The first read of a key is the one reported, because the same key is read in more than
    // one place on purpose and any of them makes the claim stale.
    $readers = [];

    foreach ($this->reads['reads'] as $read) {
        $readers[$read['path']] ??= $read;
    }

    $stale = [];

    foreach ($this->claims as $claim) {
        $read = $readers[$claim['key']] ?? null;

        if ($read === null) {
            continue;
        }

        $stale[] = sprintf(
            '%s says "%s" about %s.%s, and %s() reads it in %s:%d',
            $claim['where'],
            $claim['claim'],
            ConfigKeys::NAMESPACE,
            $claim['key'],
            $read['reader'],
            $read['file'],
            $read['line'],
        );
    }

    expect($this->published)->not->toBeEmpty()
        ->and($this->reads['reads'])->not->toBeEmpty()
        ->and($missing)->toBe([])
        ->and($stale)->toBe([]);
});

it('claims an unread key only where the record allows it', function (): void {
    // The other half of the same promise: a claim the source agrees with is still a key that
    // nothing reads, and this package's answer to one of those is to keep it published and
    // name the record that says so. `ConfigKeys::UNWIRED` is that record, and it is empty —
    // the last key in it was wired — so a live claim has to be the thing that adds an entry,
    // rather than a sentence that describes a hole nobody wrote down.
    $unrecorded = [];

    foreach ($this->claims as $claim) {
        if (array_key_exists($claim['key'], ConfigKeys::UNWIRED)) {
            continue;
        }

        $unrecorded[] = sprintf(
            '%s claims `%s` is unread, and ConfigKeys::UNWIRED has no entry for it',
            $claim['where'],
            $claim['key'],
        );
    }

    // The list is empty on purpose and the loop above therefore asserts nothing here, which is
    // the state this guard wants: a claim that survives the comparison is a claim about a key
    // nothing reads, and such a key fails ConfigKeysTest until the record is written.
    expect($unrecorded)->toBe([])
        ->and(ConfigKeys::UNWIRED)->toBe([]);
});

it('reads a claim, and a history, in the shape it says it reads', function (): void {
    // The guard above is only worth its green tick if the reader behind it works — and its
    // green tick is a scan that found no claims at all, which is exactly the shape a broken
    // reader has. So both scopes are exercised on their own: a claim is found, an account of
    // the claim having stopped being true is not — in either of the wordings this package
    // writes that account in — a marker that names no key is not, `unreadable` is not `unread`,
    // and a phrase the markdown wrapped in half is still the phrase it was written as.
    $published = ['min_length', 'br.level', 'enable_logging'];

    $docblocks = [
        'min_length' => " * The floor a body has to clear.\n * Nothing reads this value.",
        'br.level' => ' * The level of compression.',
        'enable_logging' => " * A value that is unreadable is refused.\n * It was inert, and no reader used it until the middleware was wired to it.",
    ];

    expect(ConfigClaims::docblocks($docblocks, 'fixture.php', $published))->toBe([
        [
            'key' => 'min_length',
            'claim' => 'nothing reads',
            'where' => 'fixture.php, the docblock beside `min_length`',
        ],
    ]);

    $prose = implode("\n", [
        'The floor is documented in the config file.',                     // 1
        '',                                                                // 2
        '`min_length` is published and read by nobody.',                   // 3
        '',                                                                // 4
        '`min_length` is published and never',                             // 5
        'read by anything.',                                               // 6
        '',                                                                // 7
        '`min_length` was read by nobody until the middleware was wired',  // 8
        'to it.',                                                          // 9
        '',                                                                // 10
        'Nothing reads the floor.',                                        // 11
        '',                                                                // 12
        'A value that is unreadable is refused.',                          // 13
        '',                                                                // 14
        '`br.level` is published and goes unread.',                        // 15
        '',                                                                // 16
        'Nothing reads `enable_logging`, and the middleware has been',     // 17
        'wired to it.',                                                    // 18
    ]);

    // The line the sentence begins on, not the line the phrase ends on: a claim that spans a
    // wrap is still a claim, and it is reported where a reader would start reading it.
    expect(ConfigClaims::prose($prose, 'fixture.md', $published))->toBe([
        ['key' => 'min_length', 'claim' => 'read by nobody', 'where' => 'fixture.md:3'],
        ['key' => 'min_length', 'claim' => 'never read', 'where' => 'fixture.md:5'],
        ['key' => 'br.level', 'claim' => 'goes unread', 'where' => 'fixture.md:15'],
    ]);
});
