# What does a client mean by `Accept-Encoding: identity`?

**That it would rather have the body as it is.** `identity` is the HTTP name for "no
content coding": it is a member of the negotiation like `gzip`, and a request that names
it is honoured — the response is sent with no `Content-Encoding` and nothing applied to
the body.

## Why `getEncodings()` could not answer this

`CompressResponse` read the header through `Request::getEncodings()`, which answers with
the coding names alone. Measured on this checkout:

| Request | `getEncodings()` |
|---|---|
| `identity` | `['identity']` |
| `gzip, identity` | `['gzip', 'identity']` |
| `gzip;q=0.5, identity;q=1.0` | `['identity', 'gzip']` |
| `gzip;q=1.0, identity;q=0.5` | `['gzip', 'identity']` |
| `identity;q=0, gzip` | `['gzip', 'identity']` |
| `IDENTITY` | `['IDENTITY']` |

The list is sorted by quality and then the quality is discarded, so the third row and the
fourth arrive as different orders and the second and third cannot be told apart at all —
one of them asked for no compression and the other asked for compression and got it. The
case is kept as written, so `IDENTITY` names nothing the middleware is looking for, and
`identity;q=0` — the client *refusing* an unencoded body — is indistinguishable from
`identity`, which is the client asking for one.

So `AcceptEncoding` reads the header itself, as a quality per coding.

## The rule

A client that names `identity` with a quality above zero, and no lower than the quality of
the encoding on offer, gets the body as it is. Three consequences worth being explicit
about:

- **`identity` is a veto, not a candidate.** It is not walked against the configured order
  as though it were another encoding that could win; it decides whether compressing happens
  at all. `Accept-Encoding: identity, gzip` with any order therefore leaves the body alone.
- **A tie goes to `identity`.** `gzip, identity` carries the same quality for both, so the
  client is indifferent between them, and the body it is cheaper to serve is the one already
  in hand. It is also the reading that makes this usable: a client that wants no compression
  should not have to write a quality to get it.
- **An `identity` the header does not name is not a preference.** It is what a response has
  when nothing is applied, which a client accepts unless it says otherwise. `Accept-Encoding:
  br, zstd, gzip` is untouched by all of this, and neither is `*` — a wildcard is the client
  saying it accepts anything, not that it would rather have nothing.

`identity;q=0` is the opposite of a request and is left to the acceptance rule, which keeps
compressing for `identity;q=0, gzip`. What that case really asks for is a `406` when no
acceptable coding exists, which this package does not send; it serves the body and says so
in the log. That gap is deliberate for now rather than overlooked — a `406` is a new status
this middleware would be introducing on requests that currently succeed.

## What was deliberately not changed

The quality of a client's *other* codings is still not read. The choice between encodings
remains the configured order in `multiple_encodings_order`, so `Accept-Encoding: gzip;q=1.0,
br;q=0.9` still gets `br` from the default order. Reading those qualities would change which
encoding every existing client gets, on the strength of a header that most clients write as
an unordered list of what they support.

## The alternative this replaced

Honouring `identity` only when it stood alone. That is what the code did without meaning to:
`Accept-Encoding: identity` matched no configured encoding and the body was left alone as a
side effect, so the request appeared to work, and `gzip, identity` was compressed as though
the second half of the header had not been written. A behaviour that arrives by accident is
one no test asserts and no later change has to preserve.

## Rejected for the same reason

- **Reading the position of `identity` in `getEncodings()`.** The list is quality-sorted, so
  "identity is first" looks like the answer — but it cannot distinguish a tie from a
  preference, it silently drops the difference between `q=1.0` and `q=0.0` on every other
  coding, and it is a property of a Symfony version rather than of the request.
- **Never compressing when `identity` appears anywhere in the header**, including
  `identity;q=0`. That reads a refusal as a request and leaves uncompressed the one client
  that said it could not use an uncompressed body.
- **A config switch for it.** The header is the switch. A package that has to be configured
  to obey `Accept-Encoding` is a package whose clients cannot ask.
