# How do `algorithm` and `try_multiple_encodings` relate?

**Two modes, and `algorithm` is the default one.** `algorithm` names a single encoding.
Setting `try_multiple_encodings` to true switches to walking `multiple_encodings_order`
and using the first entry the client accepts.

## Why not one mechanism

`algorithm` has to produce a working response with no configuration at all, so that
installing the package does something. An ordered list cannot: it needs an order, and
the right order depends on who the clients are. Making the list the only mode would
force every host app to answer a question most of them do not have.

The two modes also fail differently, which is worth knowing before switching:

| | `algorithm` only | `try_multiple_encodings` |
|---|---|---|
| Chosen by | config | first entry the client accepts |
| Client must accept | that one encoding | any entry in the list |
| Effort | one `match` | one pass over the list, each candidate re-checked against the client |

## How the request is gated

`handle()` asks the acceptance rule about the encodings it would actually try, before the
response is produced: that one encoding when `algorithm` is the mode, and each entry of
`multiple_encodings_order` in turn when `try_multiple_encodings` is on. The request is
gated on the first entry the client accepts.

```php
if (! $this->acceptsAnyEncoding($request, $requestUri)) {
    return $next($request);
}
```

What that buys is consistency between the gate and the walk: a client that accepts
`gzip` while `algorithm` is `br` is served `gzip` when the list holds it, and
`Accept-Encoding: *` is served the first entry of the list. The wildcard is the case
that says why the question goes to the acceptance rule rather than to the request's list
of names — no candidate is spelt `*`, so a name match finds nothing and refuses a client
whose header accepts everything. The same rule then decides each candidate again once
the response exists, so the two cannot disagree.

Only an encoding this package can produce is a candidate. `multiple_encodings_order` is a
configuration list rather than a closed set, so it can name something no encoder here answers
for — and choosing that name ends the walk, because the walk stops at the first encoding that
compressed the body, and this one compresses nothing. A walk that skipped the name is the one
that behaves: `lz4,gzip` serves a client sending `Accept-Encoding: *` with `gzip`, and a list
holding nothing producible is refused the same way a list the client accepts nothing in is.

## What happens when the list has nothing

A list that is empty, or that holds nothing this package can produce and the client accepts,
meant "no compression" silently. `fallback_behavior` decides instead, and it is read only in
this mode:

| `fallback_behavior` | what the response is |
|---|---|
| `ignore` (the default) | the body as it is, with `Vary: Accept-Encoding` |
| `compress` | the body compressed with `fallback_encoding` |

`compress` is a promise with conditions, and a condition it cannot meet becomes `ignore` with a
line in the debug log (`fallback.ignored`, naming which one failed): `fallback_encoding` unset or
empty, a name no encoder here produces, or one the client does not accept. A fallback that stopped
a response would turn one typo in a configuration into a 500, which is the trade the config
readers make everywhere else — refuse a value that is wrong, never a request because of it.

Covered by `it should use the first configured encoding the client accepts`,
`it should skip a configured encoding the client does not accept`,
`it should accept a wildcard in multiple-encoding mode`,
`it should walk the configured order when the client names only a later candidate`,
`it should skip a configured encoding it cannot produce and use the next one`,
`it should mark a response whose order list holds nothing it can produce`,
`it falls back to the configured encoding when the order list holds nothing`,
`it leaves the body alone when the order list is empty and the fallback is ignored`,
`it ignores a compression fallback it cannot produce, and says why`,
`it falls back to another encoding when the user agent refuses the primary one`,
`it serves the first configured encoding to a user agent that can decode it`, and
`it should leave the response alone when no configured encoding is accepted`.
