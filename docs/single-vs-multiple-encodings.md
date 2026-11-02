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

Covered by `it should use the first configured encoding the client accepts`,
`it should skip a configured encoding the client does not accept`,
`it should accept a wildcard in multiple-encoding mode`,
`it should walk the configured order when the client names only a later candidate`, and
`it should leave the response alone when no configured encoding is accepted`.
