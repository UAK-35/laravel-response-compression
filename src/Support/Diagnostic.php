<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Support;

/**
 * Every line the compression middleware writes, as one reason with one code.
 *
 * WHY THIS IS A TYPE RATHER THAN A SENTENCE BESIDE EACH BRANCH
 * -----------------------------------------------------------
 * The middleware used to write its diagnostics where it made the decision, which put the text of a
 * line inside the branch that wrote it. Two adjacent checks were then written with the same sentence
 * — a response that failed was recorded as a binary file — and nothing could see it: one line among
 * many looks like one line, and a test that pins a message pins it one branch at a time, so a third
 * branch copied from one of them passes every one of them.
 *
 * So a branch no longer writes a line. It names a reason, and the sentence that reason writes is
 * here, once, in `template()`. Two branches that want the same sentence have to name the same
 * reason, which is a decision rather than a copy, and this `match` is the whole of what a line can
 * say: there is nowhere else for a sentence to be introduced.
 *
 * WHY THE CODE IS IN THE LINE
 * ---------------------------
 * Every line carries its reason's code — a stable, machine-readable name for the diagnostic, dotted
 * by the thing it is about: `request.identity`, `response.not_successful`. A reader who greps a log
 * for a branch stops grepping for the words of a sentence, which is exactly what changes when the
 * sentence is improved, and two diagnostics can never be read as one another because the code sits
 * in front of the sentence in the line the log holds.
 *
 * A code is written here rather than computed from the case name, because the two spellings differ
 * by rules nobody would agree on: `CompressionChecking` is `middleware.checking`. Two cases can
 * therefore be written with one code, which is the one collision the type cannot rule out — and
 * `tests/Support/LogMessages.php` is what refuses it.
 *
 * WHAT THE VALUES ARE
 * -------------------
 * A sentence that names the request, the body or the encoding leaves a named hole where the value
 * goes — `uri`, `agent`, `length`, `minimum`, `encoding` — and `line()` fills them. A hole nothing
 * fills stays visible in the line, which is the one thing a reader can act on: `uri: {uri}` says the
 * value never arrived, where an empty string would read as a request with no path.
 */
enum Diagnostic: string
{
    /** Compression is switched off, or off under test. */
    case CompressionDisabled = 'middleware.disabled';

    /** Every enabled request, written before any branch has decided anything. */
    case CompressionChecking = 'middleware.checking';

    /** The request did not accept the configured encoding. */
    case CompressionNotAllowed = 'request.not_allowed';

    /** The client asked for the body with no content coding applied to it. */
    case IdentityRequested = 'request.identity';

    /** The header named encodings, and not one of the ones this package accepts. */
    case EncodingNotAccepted = 'request.encoding_not_accepted';

    /** The user agent is one this encoding is known to break over this connection. */
    case UserAgentNotSupported = 'request.user_agent';

    /** A binary file or a streamed response: there is no body in memory to encode. */
    case ResponseIsStream = 'response.stream';

    /** A response outside the successful range. */
    case ResponseNotSuccessful = 'response.not_successful';

    /** A response whose body is not a string for this middleware to read. */
    case ResponseNotString = 'response.not_string';

    /** A body at or under the configured floor. */
    case ResponseTooSmall = 'response.too_small';

    /** The summary written beside whichever check decided the body is left alone. */
    case ResponseSkipped = 'response.skipped';

    /** An encoding this package cannot produce, named by the order it was asked for. */
    case EncodingUnknown = 'encoding.unknown';

    /** The body was compressed, with the encoding that produced it. */
    case EncodingCompressing = 'encoding.compressing';

    /** What every line starts with, so the middleware's output can be found and filtered. */
    public const string PREFIX = '[COMPR-RESP] ';

    /**
     * What this reason says, with every value left as the named hole it goes in.
     */
    public function template(): string
    {
        return match ($this) {
            self::CompressionDisabled => 'Response compression not enabled...',
            self::CompressionChecking => 'Response compression checking - uri: {uri}',
            self::CompressionNotAllowed => 'Response compression not allowed for this request...',
            self::IdentityRequested => 'Client asked for the identity encoding (no compression) - Response compression skipped - uri: {uri}',
            self::EncodingNotAccepted => 'Response encoding not supported - Response compression skipped - uri: {uri}',
            self::UserAgentNotSupported => 'Disallowed user-agent found ( {agent}) - Response compression skipped - uri: {uri}',
            self::ResponseIsStream => 'Response is either binary file or a stream - Response compression skipped - uri: {uri}',
            self::ResponseNotSuccessful => 'Response is not successful - Response compression skipped - uri: {uri}',
            self::ResponseNotString => 'Response is not a string - Response compression skipped - uri: {uri}',
            self::ResponseTooSmall => 'Response is small ({length} < {minimum}) - Response compression skipped - uri: {uri}',
            self::ResponseSkipped => 'Response compression skipped - uri: {uri}',
            self::EncodingUnknown => 'Invalid encoding - Response compression skipped - uri: {uri}',
            self::EncodingCompressing => '>> Compressing response using first available encoding = {encoding} - uri: {uri}',
        };
    }

    /**
     * The line as the log holds it: the prefix, the code, and the sentence with its values in it.
     *
     * The hole is put back around each name before `strtr` sees it, which is not busywork: `strtr`
     * replaces the keys of its table wherever they appear, so handing it `['uri' => '/api/trips']`
     * would rewrite the word `uri` in the sentence rather than the hole it stands for.
     *
     * @param  array<string, string>  $values  a value per hole in the template, named as the hole is
     */
    public function line(array $values = []): string
    {
        $holes = [];

        foreach ($values as $name => $value) {
            $holes['{'.$name.'}'] = $value;
        }

        return self::PREFIX.$this->value.' - '.strtr($this->template(), $holes);
    }
}
