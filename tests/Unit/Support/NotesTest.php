<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Handoff;
use Uak35\ResponseCompression\Tests\Support\Notes;
use Uak35\ResponseCompression\Tests\Support\PhpBinary;

/*
|--------------------------------------------------------------------------
| The channel a reading says a skip in
|--------------------------------------------------------------------------
|
| `tests/Support/Notes` exists so that "I did not ask" and "I asked and agreed" stop being the same
| answer, and the whole of what it can get wrong is in the shape of its output: a note that does not
| name the reading, a note asked for twice arriving twice, a channel that hands the same lines to two
| readers, or a note that goes to the file a runner named *and* to the terminal, which is one sentence
| a reader meets twice. Those are asserted here, and they are all facts about this class — that a
| reading writes into it is asserted beside each reading, where the skip itself is arranged.
|
| A run of the suite can be started with a sink already named in the environment — that is how the
| gate collects what a paratest worker cannot print — and no assertion here depends on it: a note is
| made, and is owed to the terminal, whether or not a file is also being written. So the tests that
| are about the terminal arrange nothing at all, and the ones that are about the file name a file of
| their own and put the runner's arrangement back afterwards. Putting it back matters because a
| worker runs more than one file: a test that unset the variable for its own convenience would
| silence the file half of every reading that ran after it in that process.
|
| @guards-index test
*/

/**
 * The arrangement this file found, so each test that changes it can give it back.
 *
 * Read once, as the file is loaded: whatever the runner named before any test here ran. The value is
 * never changed by this file — `afterEach` below puts it back after every test — so a later file in
 * the same worker still sees the sink its runner named.
 */
$arranged = getenv(Notes::SINK);

afterEach(function () use ($arranged): void {
    if ($arranged === false) {
        putenv(Notes::SINK);

        return;
    }

    putenv(Notes::SINK.'='.$arranged);
});

/**
 * A sink this file's tests name for themselves.
 *
 * The path is named the way the other readings name theirs — under the system temp directory, with a
 * random tail — so two runs of the suite cannot write into one file.
 */
function arrangeSink(string $path): void
{
    putenv(Notes::SINK.'='.$path);
}

it('names the reading that declined, in the order the notes were made', function (): void {
    Notes::clear();

    // The reading hands in its class, because that is the name it already has; the label a reader
    // sees is the last piece of it, so a reading that had to spell out a short name of its own would
    // be a second name to keep in step with the first.
    Notes::add(Handoff::class, 'there is no machine file here');
    Notes::add(PhpBinary::class, 'the ladder was not asked');

    expect(Notes::all())->toBe([
        Notes::MARKER.' Handoff: there is no machine file here',
        Notes::MARKER.' PhpBinary: the ladder was not asked',
    ]);
});

it('keeps one copy of a note a reading makes once per thing it is asked about', function (): void {
    Notes::clear();

    // The reading asks its question per token and per document, so the same sentence arrives dozens
    // of times: a channel that kept them all would print a paragraph about the machine.
    Notes::add(Handoff::class, 'the same sentence');

    foreach (range(1, 12) as $ignored) {
        Notes::add(Handoff::class, 'the same sentence');
    }

    // And a different sentence from the same reading is a different note: the collapse is about the
    // repeat of a fact, not about a reading being allowed to say only one thing.
    Notes::add(Handoff::class, 'a second thing');

    expect(Notes::all())->toHaveCount(2);
});

it('hands the lines over once, so a harness can print what one test said', function (): void {
    Notes::clear();
    Notes::add(Handoff::class, 'said in this test');

    expect(Notes::take())->toBe([Notes::MARKER.' Handoff: said in this test'])
        ->and(Notes::all())->toBe([])
        ->and(Notes::take())->toBe([]);
});

it('writes a note to the file a runner named, and keeps the terminal too', function (): void {
    $file = sys_get_temp_dir().'/rc-notes-'.bin2hex(random_bytes(6));

    arrangeSink($file);
    Notes::clear();

    // The half CI is run on. `paratest` drops what a passing test prints — both of its streams — so a
    // note about a runner with no machine file would be lost on exactly the run it was made for, and
    // a file is what crosses a worker boundary. The line is the same one the terminal would have
    // been given, in one place, so the two readers cannot be reading two spellings.
    Notes::add(Handoff::class, 'there is no machine file here');

    // The line is the terminal's line, terminator and all: the file is written by the same method
    // that spells a printed note, so a sink and a terminal cannot disagree about the shape of one.
    //
    // And the terminal half is not given up for it. A note goes wherever it can be heard — the file
    // for the runner that reads files, the stream for whoever runs the suite by hand — and the one
    // reader that can see both places collects sentences into a set, so the same note arriving twice
    // is still one line in the gate's summary.
    expect(file_get_contents($file))->toBe(Notes::MARKER.' Handoff: there is no machine file here'.PHP_EOL)
        ->and(Notes::all())->toBe([Notes::MARKER.' Handoff: there is no machine file here'])
        ->and(Notes::take())->toBe([Notes::MARKER.' Handoff: there is no machine file here']);

    // And the file is the sink's, not this class's: nothing here takes it away, so a run may read it
    // after the last test as well as during one. The gate is what collects it, and it prints one line
    // per sentence however many workers wrote that sentence down.
    Notes::clear();

    foreach (range(1, 12) as $ignored) {
        Notes::add(Handoff::class, 'the same sentence');
    }

    // Appended, not rewritten: the file accumulates one line per sentence, and twelve asks of the
    // second sentence are still the one line this process owes — the collapse is the same here as it
    // is on the terminal, which is why the file can be read after the last test as well as during one.
    expect(file_get_contents($file))->toBe(
        Notes::MARKER.' Handoff: there is no machine file here'.PHP_EOL.
        Notes::MARKER.' Handoff: the same sentence'.PHP_EOL,
    );

    @unlink($file);
});

it('keeps a note for the terminal when the sink cannot be written', function (): void {
    // A path that cannot exist: the directory is not there, and nothing here creates one. A sink
    // that refuses a note costs nothing, because the note was never only for the sink — the run that
    // named it is told in the same breath as the run that did not, and no state has to be carried to
    // remember which of the two happened.
    $missing = sys_get_temp_dir().'/rc-notes-'.bin2hex(random_bytes(6)).'/notes';

    arrangeSink($missing);
    Notes::clear();
    Notes::add(Handoff::class, 'the file could not take this');

    expect(Notes::all())->toBe([Notes::MARKER.' Handoff: the file could not take this'])
        ->and(Notes::take())->toBe([Notes::MARKER.' Handoff: the file could not take this'])
        ->and(is_file($missing))->toBeFalse();
});
