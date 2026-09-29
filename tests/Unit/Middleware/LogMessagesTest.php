<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Uak35\ResponseCompression\Tests\Support\LogMessages;
use Uak35\ResponseCompression\Tests\Support\ResponseWithNoReadableContent;

/*
| Two skipped responses that say the same sentence
|-------------------------------------------------
|
| Two of the middleware's skip checks sat next to each other and both wrote the same line, so an
| unsuccessful response was recorded as a binary file — in the one output `enable_logging` exists to
| produce, which is where anyone looks to find out why a response was left alone. It was found by
| reading the file and fixed by hand, which is the kind of fix that comes back.
|
| Nothing at runtime can see it: one line among many looks like one line, and a test that pins a
| message pins it one branch at a time, so a third branch copied from one of them passes all of them.
| What can see it is what the branches have in common — the text they write — read out of the file
| and compared with itself.
|
| Three things are asserted, because one of them alone would be worth little: that no two branches
| write the same line, that the reading is complete enough to be trusted, and that the shapes it
| reasons about are the ones the middleware really emits.
|
*/

it('writes a different line for every branch that reports one', function (): void {
    // The report names both lines and the sentence they share, so a failure sends the reader to the
    // pair to fix rather than to the file to search.
    expect(LogMessages::duplicates(LogMessages::in(LogMessages::source())))->toBe([]);
});

it('reads every call in the middleware, not only the ones it can parse', function (): void {
    $source = LogMessages::source();

    // A reading that came up empty agrees with a file whose lines all differ, so the count is what
    // says the source was read. The reader decides where a call begins and where its argument ends;
    // this count only looks for the text, so a call it walked past shows up as a difference.
    expect(LogMessages::in($source))->toHaveCount(LogMessages::callsIn($source))
        ->and(LogMessages::callsIn($source))->toBeGreaterThanOrEqual(8);
});

it('reads a diagnostic out of text, never out of a variable alone', function (): void {
    $opaque = array_column(
        array_filter(
            LogMessages::in(LogMessages::source()),
            static fn (array $message): bool => $message['shape'] === LogMessages::OPAQUE,
        ),
        'line',
    );

    // `logDebugStatus($message)` cannot be compared with anything by reading it, and two branches
    // that did it would compare equal here for the wrong reason rather than for the right one. The
    // line is reported instead of passed over: this guard is worth what the text it can see is worth.
    expect($opaque)->toBe([]);
});

it('reports the two branches that write the same line', function (): void {
    // The defect in the shape it had: the same sentence in two adjacent checks, one of which
    // reported an unsuccessful response as a binary file.
    $source = <<<'PHP'
        <?php
        $this->logDebugStatus('Response is either binary file or a stream - Response compression skipped - uri: '.$requestUri);
        $this->logDebugStatus('Response is either binary file or a stream - Response compression skipped - uri: '.$requestUri);
        PHP;

    expect(LogMessages::duplicates(LogMessages::in($source)))->toBe([
        'lines 2 and 3 both write: Response is either binary file or a stream - Response compression skipped - uri: {}',
    ]);
});

it('leaves two branches alone when only the values in them differ', function (): void {
    // The pair of checks this guard is not about. Both name the request and the size of the body,
    // which is what makes each line useful on its own; the words around those values are what tells
    // the two branches apart, and they differ.
    $source = <<<'PHP'
        <?php
        $this->logDebugStatus('Response is small ('.$length.' < '.$min.') - Response compression skipped - uri: '.$uri);
        $this->logDebugStatus('Response is not successful - Response compression skipped - uri: '.$uri);
        PHP;

    expect(LogMessages::duplicates(LogMessages::in($source)))->toBe([]);
});

it('reads a call the way the middleware writes it', function (string $call, string $shape): void {
    expect(LogMessages::shapes("<?php \$this->{$call};"))->toBe([$shape]);
})->with([
    'a message with nothing that varies in it' => [
        "logDebugStatus('Response compression not enabled...')",
        'Response compression not enabled...',
    ],
    'a message with three values in it' => [
        "logDebugStatus('Response is small ('.\$length.' < '.\$minimum.') - Response compression skipped - uri: '.\$uri)",
        'Response is small ({} < {}) - Response compression skipped - uri: {}',
    ],
    'a value that is a call of its own' => [
        "logDebugStatus('>> Compressing response using first available encoding = '.\$this->algorithm().' - uri: '.\$uri)",
        '>> Compressing response using first available encoding = {} - uri: {}',
    ],
    'an escaped apostrophe' => [
        "logDebugStatus('It\\'s skipped - uri: '.\$uri)",
        "It's skipped - uri: {}",
    ],
    'a comma inside the message, which is not an argument separator' => [
        "logDebugStatus('the two, in either order - uri: '.\$uri)",
        'the two, in either order - uri: {}',
    ],
    'a double-quoted message, which interpolates instead' => [
        'logDebugStatus("Response is not a string - uri: {$uri}")',
        'Response is not a string - uri: {}',
    ],
    'a call written across lines' => [
        <<<'PHP'
            logDebugStatus(
                'Response compression skipped - uri: '.$requestUri
            );
            PHP,
        'Response compression skipped - uri: {}',
    ],
    'a message built from variables alone' => [
        'logDebugStatus($message)',
        '{}',
    ],
    'a message with a second argument, which is not part of the text' => [
        "logDebugStatus('[COMPR-RESP] no', ['context' => 'here'])",
        '[COMPR-RESP] no',
    ],
]);

it('writes a different line for every branch it is driven through', function (): void {
    $declared = LogMessages::shapes(LogMessages::source());

    $handler = new TestHandler;
    Log::swap(new Logger('compression', [$handler]));

    config()->set('response-compression.enable_logging', true);

    $uri = '/api/trips';
    $accepted = ['HTTP_ACCEPT_ENCODING' => 'gzip'];
    $content = getShortContent();
    $minimum = (int) config('response-compression.min_length');

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

    $normalise = static fn (string $message): string => str_replace(
        [' - uri: '.$uri, '('.strlen($content).' < '.$minimum.')'],
        [' - uri: {}', '({} < {})'],
        substr($message, strlen(LogMessages::PREFIX)),
    );

    $written = [];

    foreach ($runs as [$response, $headers, $enabled]) {
        config()->set('response-compression.enabled', $enabled);
        $handler->reset();

        runCompressResponseMiddleware(Request::create($uri, 'GET', [], [], [], $headers), $response);

        foreach ($handler->getRecords() as $record) {
            $line = $normalise((string) $record->message);

            // Two lines are written for more than one of these requests by construction, so neither is
            // what this compares: the one every enabled request writes before any branch has decided
            // anything, and the summary written beside whichever check decided the response was
            // skipped. Both name the request, which is why they repeat, and the branch's own line is
            // what tells the branches apart.
            if (in_array($line, [
                'Response compression checking - uri: {}',
                'Response compression skipped - uri: {}',
            ], true)) {
                continue;
            }

            $written[] = $line;
        }
    }

    expect(count($written))->toBeGreaterThanOrEqual(7)
        // Every line the middleware wrote is a line the reading knows about, which is what ties the
        // file to what runs: a shape declared and never emitted would make the first test weaker
        // than it reads.
        ->and(array_values(array_diff($written, $declared)))->toBe([])
        // And no two of them are the same line, reported by the messages this middleware really
        // composed rather than by the text the file contains.
        ->and(array_unique($written))->toBe($written);
});
