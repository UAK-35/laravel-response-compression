# Should a response that was not compressed still say it varies by `Accept-Encoding`?

**Because a shared cache decides what it may reuse from the headers a response names, and a body
with no `Content-Encoding` is not evidence that this URL is never compressed.** The encoders set
`Vary: Accept-Encoding` on the bodies they compress, and nothing set it on the bodies they did not -
so the two representations of one URL read to a cache as two resources, or as one. That is the
defect this record is about, and the answer is that the middleware marks every response it
considered and left alone, not only the ones it compressed.

## What goes wrong without it

A shared cache stores a response under a key that includes the request headers named in the
response's `Vary`. A body served with `Content-Encoding: br` and `Vary: Accept-Encoding` is stored
under one key per `Accept-Encoding` value, so a client that asks for `identity` is not handed the
Brotli body and a client that asks for Brotli is not handed the identity one.

The gap is the response that carries no `Content-Encoding` at all: a client that asked for
`identity`, one that named no encoding this package offers, or one served as-is because its user
agent is known to decode the encoding badly. That response said nothing about `Accept-Encoding`,
while the same URL - a larger body, or a different client - is compressed. A cache that saw only
the uncompressed body is free to reuse it for the client that asked for the encoding, and that
client receives the identity representation while its own request said otherwise. The other
direction is worse: a cache that stored a compressed body under a key that ignored
`Accept-Encoding` can hand it to a client that asked for `identity`, which is a body that client
cannot decode.

## Where the header is added, and why it is every path

`CompressResponse::handle` returns through a `vary()` call on every outcome it can reach once
compression is enabled:

- the request is refused before the body exists - `identity`, an encoding the client does not
  accept, or a user agent this package refuses for the configured encoding;
- the response is refused after the body exists - not successful, not a plain string, a stream or a
  binary file, or below `min_length`;
- the configured encoding is not one this package can produce, so the `encode()` call is a no-op;
- the multiple-encoding walk finds no encoding the client accepts.

The second group is the one worth arguing about, because the body it carries is uncompressed for
*every* client rather than only for this one: a small body is small whoever asks, and a 404 is a 404
whoever asks. Marking it anyway is deliberately the safe direction rather than the precise one. A
test on the exact request-dependent paths would have to be re-derived every time a reason is added,
and getting it wrong is silent - a missing header rather than a red build, which is the class of
defect this record exists to close. `Vary: Accept-Encoding` on a response that could not have been
compressed costs a cache a little reuse and nothing else. The header also belongs to the URL rather
than to one response - the middleware is the negotiation point for every response the URL returns -
so a cache that learned to key on it after a large response does not have to unlearn it when a small
one follows.

The check is idempotent, which is what lets it be applied where an encoder may already have run.
`Vary` is appended to rather than rewritten, so an application's own `Vary: Origin` survives beside
it, and a response an encoder has already marked is not marked twice. The encoders still set the
header for the bodies they compress; this is the other half of that sentence, and it removes a
dependence on which of the two ran first.

## Alternatives that were refused

- **Set `Vary` only where the decision depended on `Accept-Encoding`.** More precise, and the first
  shape considered. It was refused because the two groups of reasons sit one line apart in the code
  and drift apart quietly: a reason added on the response side would not fail a test, it would omit
  a header.
- **Let a second encoder-shaped pass write the header.** A second place that writes `Vary` is a
  second thing to keep in step, and the response that is not compressed has no encoder to write it.
- **Answer `406 Not Acceptable` when nothing acceptable can be produced.** Correct for
  `identity;q=0` with no acceptable coding, and a larger change than this one; it stays recorded as
  a deliberate gap in [accept-encoding-identity.md](accept-encoding-identity.md).

## What proves it

`tests/Unit/Middleware/CompressResponseTest.php` drives every path above and asserts that the header
is present, that a pre-existing `Vary` value is kept, that `Accept-Encoding` is not repeated, and
that a disabled middleware adds nothing - the last one because a feature that is off has considered
nothing.
