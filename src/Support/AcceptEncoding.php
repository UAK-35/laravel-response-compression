<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Support;

/**
 * The `Accept-Encoding` header, read as what the client asked for.
 *
 * WHY THIS EXISTS
 * ---------------
 * A request can say, in one header, that it would rather have the body as it is:
 *
 *     Accept-Encoding: identity
 *
 * `identity` is the HTTP name for "no content coding" - the body with nothing applied to it
 * - and it is a member of the negotiation like any other. The middleware read the header
 * through `Request::getEncodings()`, which answers with the coding names alone: sorted by
 * quality, with the quality itself discarded and `q=0` entries kept. A client that wrote
 * `identity;q=1.0, gzip;q=0.5` therefore arrived looking exactly like one that wrote
 * `gzip` first and `identity` second; nothing downstream could tell them apart, and the
 * body was compressed for both of them.
 *
 * WHY ONLY `identity` IS READ FOR A QUALITY
 * -----------------------------------------
 * A quality does not reorder the choice *between* encodings here. That order is the
 * package's own preference list, stated in `multiple_encodings_order`, and letting a
 * client's q-values replace it would change which encoding every existing client gets on
 * the strength of a header nobody wrote as a ranking. `identity` is the one coding this
 * class reads a quality for, because it is the one coding the package cannot produce and
 * therefore the one it has to be asked about. See docs/accept-encoding-identity.md.
 *
 * A VETO, NOT A CANDIDATE
 * -----------------------
 * `identity` is read as a veto on compressing rather than as an entry that could win the
 * walk through the configured order: when the client wants the body as it is at least as
 * much as it wants the coding on offer, the body is served as it is. An `identity` the
 * header does not name is not a preference at all - it is what a response has when nothing
 * is applied, which a client accepts unless it says otherwise - so the ordinary
 * `Accept-Encoding: br, zstd, gzip` is untouched by every rule below.
 *
 * @see \Uak35\ResponseCompression\Middleware\CompressResponse
 */
final class AcceptEncoding
{
    /** The coding that means "none": the body with no content coding applied to it. */
    public const string IDENTITY = 'identity';

    /** The coding that stands for every coding the header does not name. */
    public const string ANY = '*';

    /** The quality an entry has when it states none this can read, and the highest there is. */
    private const float PREFERRED = 1.0;

    /**
     * The header as a preference for each coding it names, keyed by the lowercased coding.
     *
     * A coding name is case-insensitive, so `IDENTITY` is `identity`; a coding written twice
     * keeps the last quality, which is what writing it twice means; and an entry with no
     * coding in it before the first `;` or `,` is not an entry at all.
     *
     * @return array<string, float>
     */
    public static function preferences(?string $header): array
    {
        $preferences = [];

        foreach (explode(',', (string) $header) as $entry) {
            $parameters = explode(';', $entry);

            // No cast and no coalesce, because there is nothing to allow for: `explode` answers with
            // a non-empty list and its first item is a string, which is what both readings say - the
            // cast this used to carry was Rector's finding and the null it allowed for was never
            // reachable.
            $coding = strtolower(trim(array_shift($parameters)));

            if ($coding === '') {
                continue;
            }

            $preferences[$coding] = self::quality($parameters);
        }

        return $preferences;
    }

    /**
     * Whether the client asked for this coding to be left alone, in this request.
     *
     * True when the header names `identity` with a quality above zero and no lower than the
     * coding's own, which is the client saying that the body as it is suits it at least as
     * well as the body this package would produce. An `identity` named with `q=0` is the
     * opposite of that - the client is refusing an unencoded body - and is left to the
     * middleware's acceptance rule, as is an `identity` the header never names.
     */
    public static function prefersIdentity(?string $header, string $coding): bool
    {
        $preferences = self::preferences($header);
        $identity = $preferences[self::IDENTITY] ?? null;

        if ($identity === null || $identity <= 0.0) {
            return false;
        }

        return $identity >= self::preferenceFor($preferences, $coding);
    }

    /**
     * What the client wants one coding: what it says about that coding, or what it says about
     * every coding it does not name.
     *
     * The last fallback is the coding nothing was said about, which is the case this class
     * exists for: `Accept-Encoding: identity` leaves the coding on offer unnamed, and the
     * question is whether the client asked for it or for the body as it is.
     *
     * @param  array<string, float>  $preferences
     */
    private static function preferenceFor(array $preferences, string $coding): float
    {
        return $preferences[strtolower($coding)]
            ?? $preferences[self::ANY]
            ?? self::PREFERRED;
    }

    /**
     * The quality one entry states, or the preferred one when it states none this can read.
     *
     * A `q` that will not parse is read as absent rather than as zero: `q=nonsense` is a
     * header parameter this cannot use, and reading it as a refusal would suppress the
     * compression of a client that never asked for that. A number outside `0..1` is clamped
     * to it, because that is the range a quality is defined over.
     *
     * @param  list<string>  $parameters
     */
    private static function quality(array $parameters): float
    {
        foreach ($parameters as $parameter) {
            [$name, $value] = array_pad(explode('=', $parameter, 2), 2, '');

            if (strtolower(trim($name)) !== 'q') {
                continue;
            }

            $value = trim($value);

            return is_numeric($value) ? min(1.0, max(0.0, (float) $value)) : self::PREFERRED;
        }

        return self::PREFERRED;
    }
}
