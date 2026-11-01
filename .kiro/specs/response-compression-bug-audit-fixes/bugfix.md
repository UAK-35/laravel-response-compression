# Bugfix Requirements Document

## Introduction

This document specifies requirements for fixing 13 bugs identified in the comprehensive audit report `docs/reports/0002-2026-10-31_rpt-config-src-tests-bug-audit.md`. These bugs span three priority levels:

- **P1 Critical (5 bugs)**: Cache safety and response correctness issues that can deliver incorrect bytes to clients or violate HTTP caching semantics
- **P2 Functional (7 bugs)**: Functional defects and ineffective regression tests that compromise feature behavior or test reliability
- **P3 Static Analysis (1 bug)**: PHPDoc mismatch causing type checker diagnostics

All bugs were runtime-reproduced with specific test cases. The fixes must ensure correct compression behavior while preserving all existing correct functionality.

---

## Bug Analysis

### Current Behavior (Defect)

#### P1: Cache Safety & Response Correctness

1.1 WHEN a response has existing `Vary` headers (e.g., `Vary: Origin, Accept-Language`) and compression is applied THEN the system replaces the entire Vary header with only `Vary: Accept-Encoding`, losing cache isolation directives

1.2 WHEN multiple encodings are configured and tried (e.g., gzip then brotli) and content is sufficiently incompressible THEN the system applies both compressions sequentially but advertises only the last encoding in `Content-Encoding`, resulting in double-compressed bodies that browsers cannot decode correctly

1.3 WHEN a response already has a `Content-Encoding` header (e.g., `Content-Encoding: gzip`) and the body is large enough THEN the system compresses the already-encoded body again, producing double-encoded content while keeping only a single encoding header

1.4 WHEN a client explicitly refuses an encoding with `q=0` (e.g., `Accept-Encoding: gzip;q=0` or `*;q=0` or `*;q=1, gzip;q=0`) THEN the system ignores the quality value and sends the refused encoding anyway

1.5 WHEN a partial response (status 206) with a `Content-Range` header is compressed THEN the system encodes the body but keeps the original byte range values, which no longer match the compressed representation size

#### P2: Functional Defects

2.1 WHEN multiple-encoding mode is enabled but the single `algorithm` config doesn't match any client-accepted encoding (e.g., `algorithm=br` but client sends `Accept-Encoding: gzip`) THEN the system returns uncompressed content even though a fallback encoding is available and accepted

2.2 WHEN brotli or zstd skip lists use `User-Agent` to refuse compression for specific clients THEN the system omits `User-Agent` from the `Vary` header, allowing shared caches to serve wrong representations to different user agents

2.3 WHEN a response has a strong `ETag` (e.g., `ETag: "original"`) and compression is applied THEN the system leaves the ETag unchanged despite producing a different byte representation, violating HTTP validator semantics

2.4 WHEN a client sends capitalized encoding names (e.g., `Accept-Encoding: GZIP`) THEN the system fails to match against the lowercase `algorithm=gzip` config and returns uncompressed content

2.5 WHEN `isBrotliEncoded()` receives valid compressed empty string (`brotli_compress('')`) or compressed zero string (`brotli_compress('0')`) THEN the system returns false despite native decompression succeeding

2.6 WHEN regression tests claim to exercise specific behaviors (pre-compressed content guard, first-candidate selection, skip-candidate fallback, Vary preservation) THEN the tests pass without actually testing those behaviors due to undersized fixtures or unchallenging configurations

2.7 WHEN `ReleaseRepo::script()` runs a child process that writes more than the stderr pipe buffer (e.g., 1 MiB diagnostic output) THEN the system deadlocks because the parent waits for stdout EOF while the child blocks on the full stderr pipe

#### P3: Static Analysis

3.1 WHEN `GuardIndex::declaration()` returns an array shape to callers THEN the PHPDoc documents only `array{section: string, line: int}|null` but the function also returns `name: ?string`, causing PHPStan to report missing offset errors on valid accesses

---

### Expected Behavior (Correct)

#### P1: Cache Safety & Response Correctness

1.1 WHEN a response has existing `Vary` headers and compression is applied THEN the system SHALL append `Accept-Encoding` to the existing Vary values using `setVary(..., false)` semantics, preserving cache isolation directives including wildcard `Vary: *`

1.2 WHEN multiple encodings are configured and tried THEN the system SHALL stop after the first successful compression and advertise only that single encoding in `Content-Encoding`, ensuring browsers can decode the body with one pass

1.3 WHEN a response already has a `Content-Encoding` header THEN the system SHALL skip compression entirely for that response, avoiding double-encoding

1.4 WHEN a client explicitly refuses an encoding with `q=0` THEN the system SHALL read the effective quality value and require it to be positive before applying that encoding, respecting explicit refusals including wildcard overrides

1.5 WHEN a partial response (status 206) has a `Content-Range` header THEN the system SHALL skip compression for that response, preserving the byte range integrity

#### P2: Functional Defects

2.1 WHEN multiple-encoding mode is enabled THEN the system SHALL negotiate against the configured candidate list directly without requiring the single `algorithm` setting to match, enabling fallback to any accepted encoding

2.2 WHEN brotli or zstd skip lists use `User-Agent` to influence compression decisions THEN the system SHALL include `User-Agent` in the `Vary` header for those responses, ensuring correct cache discrimination

2.3 WHEN a response has a strong `ETag` and compression is applied THEN the system SHALL either remove the ETag, weaken it to a weak validator, or recompute it for the encoded representation, ensuring validator correctness

2.4 WHEN a client sends encoding names in any case THEN the system SHALL normalize request coding tokens to lowercase before comparison, matching capitalized tokens correctly

2.5 WHEN `isBrotliEncoded()` receives compressed content that decompresses to valid strings including empty string or '0' THEN the system SHALL return true, distinguishing decompression success from failure

2.6 WHEN regression tests claim to exercise specific defect guards THEN the tests SHALL use fixtures and configurations that actually challenge those guards, including deterministic incompressible content above the size floor, multiple accepted candidates, disagreement with the single algorithm, and compression-time header preservation

2.7 WHEN `ReleaseRepo::script()` runs a child process THEN the system SHALL drain stdout and stderr concurrently or redirect to separate files, bound execution time, and clean up processes on timeout, preventing deadlock on large error output

#### P3: Static Analysis

3.1 WHEN `GuardIndex::declaration()` returns an array shape THEN the PHPDoc SHALL document `array{section: string, line: int, name: ?string}|null` to match the actual returned structure and eliminate false-positive PHPStan diagnostics

---

### Unchanged Behavior (Regression Prevention)

#### Core Compression Functionality

1.1 WHEN a response has no existing `Vary` header and compression is applied THEN the system SHALL CONTINUE TO add `Vary: Accept-Encoding` as the only variation directive

1.2 WHEN content is compressible on the first encoding attempt THEN the system SHALL CONTINUE TO produce correctly sized compressed bodies that decompress to the original content in one pass

1.3 WHEN a response has no `Content-Encoding` header and meets all compression criteria THEN the system SHALL CONTINUE TO compress the response body and add the appropriate encoding header

1.4 WHEN a client accepts encodings with positive quality values (e.g., `Accept-Encoding: gzip` or `gzip;q=0.8`) THEN the system SHALL CONTINUE TO apply the configured compression algorithm

1.5 WHEN a successful response (status 200) with a complete body is compressed THEN the system SHALL CONTINUE TO produce correctly encoded content without range headers

#### Negotiation and Configuration

2.1 WHEN the single `algorithm` config matches an accepted client encoding in single-encoding mode THEN the system SHALL CONTINUE TO compress using that algorithm

2.2 WHEN user-agent skip lists are empty or a request's User-Agent is not in any skip list THEN the system SHALL CONTINUE TO apply compression based solely on `Accept-Encoding`

2.3 WHEN a response has no `ETag` header or has a weak ETag (e.g., `W/"tag"`) THEN the system SHALL CONTINUE TO preserve or handle those validators as currently implemented

2.4 WHEN a client sends lowercase encoding names (e.g., `Accept-Encoding: gzip, br`) THEN the system SHALL CONTINUE TO match them correctly against config values

2.5 WHEN `isBrotliEncoded()` receives valid compressed non-empty, non-zero content THEN the system SHALL CONTINUE TO return true for successful decompression

2.6 WHEN regression tests use appropriately sized fixtures with challenging configurations THEN the tests SHALL CONTINUE TO pass and catch the defects they are designed for

2.7 WHEN `ReleaseRepo::script()` runs a child process with normal-sized output THEN the system SHALL CONTINUE TO capture and return both stdout and stderr correctly

#### Validation and Skip Conditions

3.1 WHEN responses fail existing validation checks (wrong content type, unsuccessful status excluding 206, body too small, streamed response, missing extensions) THEN the system SHALL CONTINUE TO skip compression with the same behavior

3.2 WHEN content is below the configured `min_length` threshold THEN the system SHALL CONTINUE TO skip compression

3.3 WHEN a client requests `identity` encoding explicitly or sends no `Accept-Encoding` header THEN the system SHALL CONTINUE TO handle these cases per the existing `prefersIdentity()` logic

3.4 WHEN configured algorithm strings don't match known encodings THEN the system SHALL CONTINUE TO leave those responses uncompressed

3.5 WHEN the gate runs syntax checks, PHPStan on `src/` only, Rector, Pint, and the full test suite THEN all checks SHALL CONTINUE TO pass with the same coverage floors (100.0% for all `src/` files)

3.6 WHEN `python .agents/verify.py` runs all 14 verification steps THEN the system SHALL CONTINUE TO report zero findings

3.7 WHEN the pre-commit hook runs `bin/checks.php --staged` THEN it SHALL CONTINUE TO validate staged files correctly

#### Public API and Contracts

4.1 WHEN consumers install the package and publish the config THEN they SHALL CONTINUE TO receive `config/response-compression.php` with all documented keys and sensible defaults

4.2 WHEN applications register the middleware in their HTTP kernel THEN compression SHALL CONTINUE TO apply transparently based on configuration

4.3 WHEN the middleware is disabled via config THEN responses SHALL CONTINUE TO pass through unmodified

4.4 WHEN encoder classes are instantiated and used THEN their public methods SHALL CONTINUE TO compress content with the appropriate algorithm and return encoded responses

4.5 WHEN `src/Support/Config.php` reads configuration THEN it SHALL CONTINUE TO validate and provide access to all config keys without falling back to silent defaults

4.6 WHEN `GuardIndex` callers access the `section`, `line`, and `name` fields of returned declarations THEN they SHALL CONTINUE TO receive valid values for existing guards

