# Bug audit of config, source and tests

Created: 2026-10-31 (Asia/Karachi, UTC+05:00). Report: 0002.
Status: findings recorded; implementation fixes not applied.

## Scope and evidence

Reviewed the published `config/response-compression.php`, all classes under `src/`, the middleware,
encoder and configuration tests, and test support code concerned with processes, coverage and
repository guards. PHP lint, PHPStan and Rector were also run over the entire requested PHP corpus,
including the rest of `tests/`. Manual inspection of the much larger guard and release test tree
was selective; this is not a proof that every possible defect in those helpers has been found.

Runtime reproductions used the installed Composer dependencies, PHP 8.4.27, PCOV 1.0.12 and the real
gzip, brotli and zstd extensions. A small Laravel application container supplied the shipped config
and invoked `CompressResponse::handle()` with real Symfony requests and responses. Experiments and
logs are in the ignored `build/bug-audit/` directory. No source, config or test implementation was
changed. Existing uncommitted work was preserved.

The report inventory contained only `0001-2026-10-30_rpt-agentic-setup-audit.md`. Its recorded original
creation date is 2026-10-30 in Asia/Karachi; Git has no addition history for that untracked report.
The filesystem creation timestamp is consistent with the recorded date after timezone conversion,
but is not independent authorship evidence. Its recorded date places it before this report, so the
existing name is retained and this report is appended as `0002`; no renaming is needed.

Priorities: P1 means incorrect response bytes or cache behavior that warrants an early fix; P2 means
a functional defect or a misleading regression test; P3 means a static-analysis contract defect.
Findings 1–10 were reproduced through public runtime methods. Finding 11 follows from the actual
fixtures and reproduced failures. Finding 12 has a bounded process reproduction. Finding 13 is a
confirmed PHPDoc mismatch, not a demonstrated runtime crash.

## Findings — runtime and test logic

1. **[P1] Compression overwrites existing cache variation headers.**

   Locations: `src/Encoders/GzipEncoder.php:20–24`, `BrotliEncoder.php:22–26`,
   `ZstdEncoder.php:22–26`; `src/Middleware/CompressResponse.php:96–108`.

   Each encoder calls `headers->add()` with a new `Vary` value. That replaces the original header;
   the middleware's later `vary()` call sees `Accept-Encoding` and returns without restoring it.
   Reproduced with `Vary: Origin, Accept-Language`: gzip, br and zstd each returned only
   `Vary: Accept-Encoding`. A shared cache can consequently reuse a response across origins or
   languages that the application explicitly distinguished. An existing `Vary: *` is also vulnerable
   to replacement. Append variation with `setVary(..., false)` while preserving wildcard semantics.
   Add compressed-response cases for existing variation headers; the current preservation test at
   `tests/Unit/Middleware/CompressResponseTest.php:582` requests `identity`, so no encoder runs.

2. **[P1] The multiple-encoding loop applies several compressions but advertises only the last.**

   Location: `src/Middleware/CompressResponse.php:62–69`, with the encoder header writes above.

   There is no stop after a successful encoding. With `try_multiple_encodings=true`,
   `algorithm=gzip`, order `gzip,br`, and `Accept-Encoding: gzip, br`, a sufficiently incompressible
   body remains over `min_length` after gzip and is then compressed with brotli. The response says
   only `Content-Encoding: br`. Reproduced with base64 of 20,000 random bytes: decoding brotli once
   produced a gzip stream, not the original body. Browsers therefore receive encoded bytes after
   following the advertised decoding instruction. Stop after the first successful candidate, as the
   existing test name promises. Test at least two accepted candidates with a fixture whose first
   compressed representation still exceeds the floor, and assert exact one-step round trips.

3. **[P1] Already encoded responses are compressed again.**

   Location: `src/Middleware/CompressResponse.php:148–193`.

   Response validation checks type, status and length, but never existing `Content-Encoding`.
   A downstream response with a sufficiently large gzip body and `Content-Encoding: gzip` is
   gzip-compressed again while retaining a single `gzip` header. Reproduced using gzip of the
   incompressible fixture: one `gzdecode()` returned another gzip stream. The same missing check can
   overwrite a different downstream encoding. Skip responses that already carry a content coding
   the middleware is not explicitly designed to compose. The current purported regression test is
   ineffective for this case; see finding 11.

4. **[P1] Encodings explicitly refused with `q=0` are still sent.**

   Locations: `src/Middleware/CompressResponse.php:246–272`;
   `src/Support/AcceptEncoding.php:97–109`.

   `Request::getEncodings()` retains entries with zero quality, and the middleware uses membership
   rather than quality to decide acceptance. `prefersIdentity()` only vetoes when positive
   `identity` is explicitly present. With `algorithm=gzip`, all of `gzip;q=0`, `*;q=0`, and
   `*;q=1, gzip;q=0` produced gzip in the reproduction. These are refusals, including an explicit
   coding override of the wildcard. Read effective coding quality before compression and require it
   to be positive. This can preserve the configured server order among acceptable candidates.
   Add integration cases, rather than only parser tests comparing identity preferences.

5. **[P1] Partial responses retain byte ranges for a representation that has been replaced.**

   Locations: `src/Middleware/CompressResponse.php:163–193`, and each encoder's `handle()`.

   `isSuccessful()` includes status 206. An in-memory range response can therefore be compressed
   after its body and `Content-Range` were selected. Reproduced with an 11,000-byte body, status 206
   and `Content-Range: bytes 0-10999/20000`: the result carried a 73-byte gzip body with the original
   range unchanged. That range does not describe the new encoded representation; clients assembling
   ranges or resuming a resource can receive inconsistent data. Skip responses with a range status
   or range header unless coding and range selection are coordinated. Add a long-body 206 test;
   ordinary successful/non-successful coverage does not establish range correctness.

6. **[P2] Multiple-encoding fallback is gated on the unrelated single-algorithm setting.**

   Locations: `src/Middleware/CompressResponse.php:53–63` and `138–147`.

   `validateRequest()` checks only `algorithm` before `tryMultipleEncodings()` is considered.
   With the shipped `algorithm=br`, enabling multiple encodings and receiving a gzip-only client
   returns the uncompressed body even though gzip is in the configured order. The reproduction
   returned no `Content-Encoding`. The same gate prevents fallback when the user agent is refused
   for the primary algorithm but allowed for another one. In multiple mode, negotiate against the
   candidate list directly. Add a case where the single algorithm is not accepted and a later
   candidate is accepted; current tests change the single algorithm to an accepted one.

7. **[P2] User-agent-dependent representations are not marked as varying by user agent.**

   Locations: `src/Middleware/CompressResponse.php:96–108` and `274–291`;
   `config/response-compression.php:51–56,72–78`.

   The shipped brotli and zstd prefix lists change compression based on `User-Agent`, while the
   response varies only by `Accept-Encoding`. Reproduced with the same `Accept-Encoding: br`:
   `axios/1.7.2` received identity and `Mozilla/5.0` received brotli; both responses had only
   `Vary: Accept-Encoding`. For a publicly cacheable application response, a shared cache cannot
   distinguish those clients and can deliver a coding to a client the package deliberately refused.
   Include `User-Agent` in variation when that header influences selection, or remove that
   negotiation dependency through a deliberate behavior change. Test both clients through a cache
   key model, not just individual compression decisions.

8. **[P2] Compression leaves a strong ETag unchanged across different representation bytes.**

   Locations: `src/Encoders/GzipEncoder.php:17–25`, `BrotliEncoder.php:19–27`,
   `ZstdEncoder.php:19–27`.

   The encoders replace content without adjusting its validator. Reproduced by setting
   `ETag: "original"`: the 11,000-byte identity representation and 73-byte gzip representation
   retained the same strong tag. A strong validator is thereby shared by byte-distinct
   representations, which undermines conditional requests and range validators when the application
   computed the tag before compression. Remove, weaken where appropriate, or recompute the strong
   tag for the encoded representation. Add tests for existing strong and weak validators and the
   intended ordering with application conditional-response middleware.

9. **[P2] Case-insensitive coding names are compared case-sensitively.**

   Locations: `src/Middleware/CompressResponse.php:267` and `138–145`.

   The package's quality parser lowercases coding names, but the acceptance path still compares
   Symfony's coding strings against lowercase config names. With `algorithm=gzip`, `Accept-Encoding:
   GZIP` returned identity; the equivalent lowercase header compressed successfully. Normalize
   request coding tokens before comparison, preferably using the same parsed preference map as the
   quality check. The existing capitalized-identity test does not cover capitalized compression
   tokens, because identity is evaluated by the separate normalized parser.

10. **[P2] The brotli detector rejects valid compressed empty and zero-string content.**

    Location: `src/Support/Enc.php:24–26`;
    `tests/Unit/Support/EncTest.php:25–47`.

    `isBrotliEncoded()` requires the decoded value to be neither `''` nor `'0'`, although both are
    valid original strings. Reproduced with `brotli_compress('')` and `brotli_compress('0')`: native
    decompression returned the exact original string, but the public helper returned false in both
    cases. Distinguish decompression failure (`false`) from valid decoded strings. Test compressed
    forms of empty and zero content separately from an empty, unencoded input. This helper is used
    by tests and is public API; the middleware itself does not call it to avoid double compression.

11. **[P2] Regression fixtures pass without exercising the behavior their names promise.**

    Locations: `tests/Unit/Middleware/CompressResponseTest.php:191–203,278–305,582–590`;
    `tests/Pest.php:68–71`; `tests/TestCase.php:18`.

    The pre-compressed-content test gzips 11,000 bytes of repeated text. Its result is far below the
    configured 1,024-byte minimum, so the middleware leaves it alone solely because it is small.
    There is no encoding-header guard to exercise. The first-candidate test supplies only one
    accepted coding, and the skip-candidate test sets the single algorithm to the accepted coding;
    neither challenges findings 2 and 6. The Vary-preservation test requests identity and bypasses
    all encoder header writes. These tests are green while the corresponding runtime defects are
    present. Use deterministic incompressible fixtures, assert their compressed size exceeds the
    floor before exercising the guard, accept two candidates in the first-candidate test, disagree
    with `algorithm` in the fallback test, and exercise header preservation during compression.
    These are test defects explaining findings 1–3 and 6, not additional independent runtime bugs.

12. **[P2] The release test helper can deadlock on a child's error output.**

    Location: `tests/Support/ReleaseRepo.php:416–431`.

    `script()` drains stdout to EOF before reading stderr. A child that fills its stderr pipe
    blocks before closing stdout, while the parent waits for stdout to close. There is no execution
    timeout; `Processes` only bounds retries of a refused process start. A failing tested script
    with a large diagnostic can therefore hang the test run instead of reporting its failure.
    Reproduced by invoking `ReleaseRepo::script()` against a scratch script that writes 1 MiB to
    stderr before emitting stdout. After 20 seconds the child had started but had not completed its
    stderr write, and the parent had not returned. A watchdog then terminated only that probe's
    process tree. Drain both streams concurrently or redirect each to a separate file, and bound
    execution time with process cleanup. Add a bounded regression case with large output on each
    stream. This reproduction did not require a Git fixture or access outside this repository.

## Finding — static type contract

13. **[P3] Guard declaration PHPDoc omits a field the function returns and its callers consume.**

    Location: `tests/Support/GuardIndex.php:1388–1407`; callers at `422`, `572` and `591`.

    `declaration()` documents `array{section: string, line: int}|null` but also returns
    `name: ?string`. PHPStan consequently reports missing `name` offsets in callers, despite those
    accesses being valid against the actual returned value. Correct the array-shape declaration
    instead of treating those particular diagnostics as evidence of missing runtime keys. Add
    appropriate types for the remaining values before enabling max-level analysis of the test tree.

## Configuration observations and exclusions

The shipped config parses and supplies the required keys. Its default `br` selection is material
to finding 6, and its default user-agent lists are material to finding 7; no independent executable
syntax defect was found in the config file. This audit did not classify documented policies such as
server candidate ordering, leaving unknown algorithm strings uncompressed, or the inclusive
minimum-length skip as bugs merely because another policy is possible.

A `Cache-Control: no-transform` response was also observed being compressed. It is excluded from
the findings: this package runs inside an origin application, and the observation alone does not
establish an intermediary violating that directive. No web-server, proxy or deployed cache was
available; cache consequences above follow from the reproduced headers and selection inputs.

## Verification results

Verification was performed on the existing dirty checkout. Tool diagnostics are kept distinct from
confirmed behavioral findings; in particular, test-framework magic, incomplete iterable types and
inaccurate PHPDoc account for a substantial part of the expanded PHPStan output.

- PHP lint over `config`, `src` and `tests`: **115 files passed**, no syntax failures.
- `composer test:types -- config src tests`: **223 diagnostics**, exit 1. The default
  `phpstan.neon.dist` analyzes only `src`; including tests deliberately expands that scope.
- `composer test:refacto -- config src tests`: **passed**, no proposed changes.
- `composer test:unit`: **528 tests passed, 1,737 assertions**, 410.53 seconds; every measured
  `src/` file reached **100.0%**, and every `bin/` coverage floor passed. This is line coverage;
  the reproduced failures show that the input combinations and assertions are still incomplete.
- `python .agents/verify.py`: **passed**, all 14 steps, zero findings. Its gate step also ran
  `composer checks -- --require-all` successfully, including the default source-only PHPStan run.
- The package's machine-path, repository-escape and documentation-link readings were also called
  directly after writing the report and changelog entry: **no findings**.
- Focused runtime reproduction: the cases described in findings 1–10 produced the reported results.
- Bounded stderr-flood reproduction: parent and child failed to complete before the 20-second
  deadline; the watchdog cleaned up that probe's process tree.

Commands were executed in PowerShell 7 through the package-resolved interpreter and Composer/tool
wrappers. Full logs and scratch programs are local evidence under `build/bug-audit/` and are not
part of the requested durable report. The report and its changelog bullet are the intended changes.
