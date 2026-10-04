<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Uak35\ResponseCompression\Support\Diagnostic;
use Uak35\ResponseCompression\Tests\Support\LogMessages;
use Uak35\ResponseCompression\Tests\Support\ResponseWithNoReadableContent;

/*
| Every diagnostic declared once, with a code it is logged under
|---------------------------------------------------------------
|
| Two of the middleware's skip checks sat next to each other and both wrote the same line, so an
| unsuccessful response was recorded as a binary file — in the one output `enable_logging` exists to
| produce, which is where anyone looks to find out why a response was left alone. Nothing at runtime
| could see it: one line among many looks like one line, and a test that pins a message pins it one
| branch at a time, so a third branch copied from one of them passes all of them.
|
| The text is no longer in the middleware. A branch names a `Diagnostic`, the sentence is written
| once, and the code the reason is logged under is in front of every line — so a branch cannot write
| a sentence of its own and two lines can never be read as one another. What is left is the two ways
| the one file can still be written wrong, and both are asserted here: two cases with one code, and
| two cases with one sentence.
|
| Three more things are asserted, because one of them alone would be worth little: that the reading
| read every case the type declares rather than part of it, that every reason writes a line carrying
| its code, and that the lines the middleware really emits are the reasons that file declares.
|
*/

/**
 * @guards-index guard the log-message test
 *
 * two reasons the middleware reports a skip as carrying one code, or two that write the same sentence — read out of the type that holds each sentence once, so a line a branch invents and a line two branches share are both the file's to refuse, and nothing in PHP refuses the duplicate code a backed enum is loaded with
 */
it('declares one code and one sentence per reason, and no two the same', function (): void {
    // The report names both cases and the code or the sentence they share, so a failure sends the
    // reader to the pair to fix rather than to the file to search.
    $declared = LogMessages::declaredIn(LogMessages::source());

    expect($declared)->not->toBeEmpty()
        ->and(LogMessages::duplicates($declared))->toBe([]);
});

it('reads every case the type declares, in the order it declares them', function (): void {
    // A reading that found six of thirteen reasons agrees with itself, so the codes it read are held
    // to the type itself: the same codes, in the same order, is what says it read the file rather
    // than part of it. The case values are the answer to a question the two cannot both be wrong
    // about — the class is the type, and the text is the file the class is written in.
    $declared = LogMessages::declaredIn(LogMessages::source());

    expect(array_column($declared, 'code'))
        ->toBe(array_map(static fn (Diagnostic $reason): string => $reason->value, Diagnostic::cases()))
        // And the sentences the reader decoded are the ones the type would write, which is what makes
        // this a reading of the same file rather than of another one that happens to look like it.
        ->and(array_column($declared, 'template'))
        ->toBe(array_map(static fn (Diagnostic $reason): string => $reason->template(), Diagnostic::cases()));
});

it('gives every reason a code to log it under and a sentence to write', function (): void {
    // Every case, not a sample of them: `template()` is a `match` with an arm each, so a case nobody
    // asks about is an arm no test has ever run.
    foreach (Diagnostic::cases() as $reason) {
        expect($reason->value)->not->toBe('')
            ->and($reason->template())->not->toBe('')
            ->and($reason->line())->toStartWith(Diagnostic::PREFIX.$reason->value.' - ');
    }
});

it('writes the code into the line, with the values in the sentence', function (): void {
    expect(Diagnostic::CompressionDisabled->line())
        ->toBe('[COMPR-RESP] middleware.disabled - Response compression not enabled...')

        // Three holes in one sentence, filled in the order the sentence names them rather than in the
        // order the values happen to be written in.
        ->and(Diagnostic::ResponseTooSmall->line(['minimum' => '2048', 'uri' => '/api/trips', 'length' => '12']))
        ->toBe('[COMPR-RESP] response.too_small - Response is small (12 < 2048) - Response compression skipped - uri: /api/trips')

        // A hole nothing filled stays visible instead of becoming an empty string: a line that says
        // `uri: {uri}` is one a reader can act on, where `uri: ` is one that reads as a request with
        // no path.
        ->and(Diagnostic::ResponseSkipped->line())->toBe('[COMPR-RESP] response.skipped - Response compression skipped - uri: {uri}')

        // A value is inserted, never rescanned: the `{uri}` written into the path below is a path a
        // request named, and it must not be filled by the `uri` beside it.
        ->and(Diagnostic::ResponseSkipped->line(['uri' => '/api/{uri}/trips']))
        ->toBe('[COMPR-RESP] response.skipped - Response compression skipped - uri: /api/{uri}/trips');
});

it('reports two reasons written with one code', function (): void {
    $source = <<<'PHP'
        <?php

        enum Fixture: string
        {
            case One = 'one.code';
            case Two = 'one.code';

            public function template(): string
            {
                return match ($this) {
                    self::One => 'the first sentence - uri: {uri}',
                    self::Two => 'the second sentence - uri: {uri}',
                };
            }
        }
        PHP;

    // The collision PHP itself does not refuse: two cases of one backed enum may hold the same value,
    // and the error only arrives when the enum is asked for a case by value, which nothing here does.
    expect(LogMessages::duplicates(LogMessages::declaredIn($source)))
        ->toBe(['One and Two both carry the code one.code']);
});

it('reports two reasons written with one sentence', function (): void {
    $source = <<<'PHP'
        <?php

        enum Fixture: string
        {
            case One = 'one.code';
            case Two = 'two.code';

            public function template(): string
            {
                return match ($this) {
                    self::One => 'the same sentence - uri: {uri}',
                    self::Two => 'the same sentence - uri: {uri}',
                };
            }
        }
        PHP;

    // The defect in the shape it had: two checks beside each other writing the same words, which is
    // what put an unsuccessful response in the log as a binary file.
    expect(LogMessages::duplicates(LogMessages::declaredIn($source)))
        ->toBe(['One and Two write the same line: the same sentence - uri: {uri}']);
});

it('refuses a file it could not read, rather than agreeing with one', function (): void {
    // Each of these is a way the reading could answer "nothing is wrong" for a file it never
    // understood, which on a suite that runs it is a guard that has stopped guarding.
    expect(fn (): array => LogMessages::declaredIn("<?php\n\nenum Nothing {}\n"))
        ->toThrow(RuntimeException::class, 'declares 0 case(s) and writes 0 sentence(s) to read')

        // A reason that exists and says nowhere what it writes: a branch could name it and the log
        // would hold an empty line.
        ->and(fn (): array => LogMessages::declaredIn("<?php\ncase Lonely = 'lonely.code';\nself::Other => 'a sentence',\n"))
        ->toThrow(RuntimeException::class, 'declares Lonely and says nowhere what it writes')

        // And the other way: a sentence with no case behind it, which nothing can ever write.
        ->and(fn (): array => LogMessages::declaredIn("<?php\ncase Lonely = 'lonely.code';\nself::Lonely => 'a sentence',\nself::Other => 'another one',\n"))
        ->toThrow(RuntimeException::class, 'writes a sentence for Other, which it does not declare');
});

it('writes a line for every branch it is driven through, and every one of them is declared', function (): void {
    $declared = array_column(LogMessages::declaredIn(LogMessages::source()), 'code');

    $handler = new TestHandler;
    Log::swap(new Logger('compression', [$handler]));

    config()->set('response-compression.enable_logging', true);

    $uri = '/api/trips';
    $accepted = ['HTTP_ACCEPT_ENCODING' => 'gzip'];
    $content = getShortContent();

    // One request per skip branch, each with the response or the headers that reach it. A streamed
    // response and a binary file are one check, so only one of them is driven: driving both would
    // record one branch's line twice and read like two branches that write the same thing. The last
    // request reaches two branches — a request that names no encoding is refused by the encoding
    // check, and then by the request check that called it.
    $runs = [
        'compression switched off' => [new Response($content, 200, ['Content-Type' => 'text/plain']), $accepted, false],
        'a response that failed' => [new Response('error', 500), $accepted, true],
        'a response that is not a string' => [
            new ResponseWithNoReadableContent(getLongContent(), 200, ['Content-Type' => 'text/plain']),
            $accepted,
            true,
        ],
        'a streamed response' => [new StreamedResponse(static fn (): string => 'ok'), $accepted, true],
        'a body under the floor' => [new Response($content, 200, ['Content-Type' => 'text/plain']), $accepted, true],
        'a request that accepts nothing' => [new Response(getLongContent(), 200, ['Content-Type' => 'text/plain']), [], true],
    ];

    $written = [];
    $codes = [];

    foreach ($runs as [$response, $headers, $enabled]) {
        config()->set('response-compression.enabled', $enabled);
        $handler->reset();

        runCompressResponseMiddleware(Request::create($uri, 'GET', [], [], [], $headers), $response);

        foreach ($handler->getRecords() as $record) {
            $line = (string) $record->message;

            // Two lines are written for more than one of these requests by construction, so neither is
            // what this compares: the one every enabled request writes before any branch has decided
            // anything, and the summary written beside whichever check decided the response was
            // skipped. Both name the request, which is why they repeat, and the branch's own line is
            // what tells the branches apart.
            if (in_array($line, [
                Diagnostic::CompressionChecking->line(['uri' => $uri]),
                Diagnostic::ResponseSkipped->line(['uri' => $uri]),
            ], true)) {
                continue;
            }

            $written[] = $line;
            $codes[] = (string) strstr(substr($line, strlen(Diagnostic::PREFIX)), ' - ', true);
        }
    }

    expect(count($written))->toBeGreaterThanOrEqual(7)

        // Every code the middleware wrote is a code the type declares, which is what ties the file to
        // what runs: a reason declared and never emitted would make the first test weaker than it
        // reads, and this is the direction that catches the other one.
        ->and(array_values(array_diff($codes, $declared)))->toBe([])

        // And no two of them are the same line, which the code in front of each is what guarantees:
        // it is reported by the messages the middleware really composed rather than by the file.
        ->and(array_unique($written))->toBe($written);
});
