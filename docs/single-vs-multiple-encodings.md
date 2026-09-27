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

## How the request is gated — and the limitation that follows

`handle()` validates the request against the configured `algorithm` *before* the
response is produced, in both modes:

```php
if (! $this->validateRequest($request)) {
    return $next($request);
}
```

So in multiple mode a request must **also** accept `algorithm` for the order list to be
consulted at all. A client that accepts `gzip` while `algorithm` is `br` is refused
before the list is ever read, even if `gzip` is in the list.

**Recorded, not fixed.** The consequence is a configuration rule rather than a bug to
patch quietly: set `algorithm` to the encoding you expect most of your clients to
accept, and the list covers the rest. The alternative — gating on "any entry the client
accepts" in multiple mode — is a behaviour change with its own tests to write, and the
suite currently pins the present behaviour by setting `algorithm` to a value each
multiple-mode test's request does accept.

Covered by `it should use the first configured encoding the client accepts`,
`it should skip a configured encoding the client does not accept`, and
`it should leave the response alone when no configured encoding is accepted`.
