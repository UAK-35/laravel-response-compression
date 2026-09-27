# Which encoding should the default order try first?

**`br,zstd,gzip`** — brotli, then zstandard, then gzip.

## What each position buys

| Position | Encoding | Why here |
|---|---|---|
| 1 | `br` | Every browser of the last several years accepts it over HTTPS, and it beats gzip on JSON and text at comparable CPU. It is first because it is the best answer for the largest group of clients. |
| 2 | `zstd` | Beats gzip on the same content for clients that accept it, and app clients (Dart/Flutter, Go, Node, `curl`) increasingly do — but browser support is patchier than brotli's, so it cannot lead. |
| 3 | `gzip` | Universal, and therefore the floor. Anything that reaches this position and accepts nothing else still gets a compressed body. |

The order is a **preference list**, not a negotiation: the first entry the client
accepts wins. Quality values (`q=`) are not consulted, and Symfony's
`getEncodings()` order is not treated as a priority — see
[single-vs-multiple-encodings.md](single-vs-multiple-encodings.md) for how the list is
walked.

## Changing it

`multiple_encodings_order` is a comma-separated string, parsed (and trimmed) by
`CompressResponse::encodingOrder()`. Two configurations the README already suggests:

- a frontend that decodes zstd → `zstd,br,gzip`
- the default, for a mixed client population → `br,zstd,gzip`

Two conditions apply. `ext-zstd` must be in `composer.json`'s `require` block and the
`libzstd` system library installed, or the zstd encoder is a no-op
(`BrotliEncoder`/`ZstdEncoder` guard on `extension_loaded()`); and the list is only
consulted when `try_multiple_encodings` is on — otherwise the single `algorithm` key
decides.
