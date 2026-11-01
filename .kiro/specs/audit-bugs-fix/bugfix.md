# Bugfix Requirements Document

## Introduction

This document addresses 13 bugs identified in the Laravel Response Compression package audit report (0002-2026-10-31). These bugs affect cache safety, HTTP content negotiation, compression correctness, and test reliability. The bugs span three priority levels:

- **P1 (Critical)**: Runtime correctness and cache safety issues that can cause incorrect response bytes or cache behavior
- **P2 (Functional)**: Functional defects and misleading regression tests
- **P3 (Static Analysis)**: Type contract defects in PHPDoc declarations

The affected components include compression middleware, individual encoders (gzip, brotli, zstd), content negotiation logic, detection helpers, and test infrastructure.

## Bug Analysis

### Current Behavior (Defect)

#### P1: Critical Runtime and Cache Safety Issues

1.1 WHEN a response has existing Vary headers (e.g., `Vary: Origin, Accept-Language`) THEN the system overwrites them with only `Vary: Accept-Encoding`, losing the original cache variation markers

1.2 WHEN multiple encodings are attempted with `try_multiple_encodings=true` and incompressible content THEN the system applies multiple compressions sequentially but advertises only the last encoding in the `Content-Encoding` header

1.3 WHEN a response already has a `Content-Encoding: gzip` header with compressed content THEN the system compresses it again without checking for existing encoding

1.4 WHEN a client sends `Accept-Encoding: gzip;q=0` or `Accept-Encoding: *;q=0` explicitly refusing an encoding THEN the system still compresses with that encoding, ignoring the quality value

1.5 WHEN a partial response (HTTP 206) with `Content-Range` header is compressed THEN the system retains the original byte range that no longer describes the compressed representation

#### P2: Functional Defects

1.6 WHEN `algorithm=br` is configured and a client accepts only `gzip` with `try_multiple_encodings=true` THEN the system returns uncompressed content because fallback is gated on the single-algorithm setting

1.7 WHEN user-agent-dependent compression is applied (brotli/zstd prefix lists check `User-Agent`) THEN the system does not include `User-Agent` in the `Vary` header, allowing cache reuse across agents with different compression decisions

1.8 WHEN a response has a strong `ETag: "original"` and compression is applied THEN the system leaves the ETag unchanged despite the representation bytes being completely different

1.9 WHEN a client sends capitalized encoding names like `Accept-Encoding: GZIP` THEN the system fails to match them case-insensitively against lowercase configuration values

1.10 WHEN `isBrotliEncoded()` receives compressed empty string or compressed `'0'` string THEN the system incorrectly returns false despite these being valid brotli-compressed content

#### P2: Test Infrastructure Defects

1.11 WHEN regression tests for pre-compressed content, first-candidate selection, and Vary preservation run THEN the system passes without exercising the promised behavior because fixtures fall below minimum size or use identity encoding

1.12 WHEN `ReleaseRepo::script()` executes a child process that writes large output to stderr THEN the system deadlocks because stdout is drained to EOF before reading stderr

#### P3: Static Analysis Contract Defect

1.13 WHEN `GuardIndex::declaration()` returns an array to callers THEN the system's PHPDoc omits the `name` field that is actually returned and consumed by callers

### Expected Behavior (Correct)

#### P1: Critical Runtime and Cache Safety Fixes

2.1 WHEN a response has existing Vary headers THEN the system SHALL append `Accept-Encoding` to the existing variation markers using `setVary(..., false)` and preserve wildcard semantics

2.2 WHEN multiple encodings are attempted THEN the system SHALL stop after the first successful compression and advertise only that encoding

2.3 WHEN a response already has a `Content-Encoding` header THEN the system SHALL skip compression to avoid double-encoding

2.4 WHEN a client explicitly refuses an encoding with `q=0` THEN the system SHALL check the effective quality value and require it to be positive before compressing

2.5 WHEN a partial response (HTTP 206) has a `Content-Range` header THEN the system SHALL skip compression unless coding and range selection are coordinated

#### P2: Functional Fixes

2.6 WHEN multiple-encoding mode is enabled and the single algorithm is not accepted THEN the system SHALL negotiate directly against the candidate list without requiring the single algorithm to match

2.7 WHEN compression decisions depend on `User-Agent` header THEN the system SHALL include `User-Agent` in the `Vary` header

2.8 WHEN a response has a strong ETag and compression is applied THEN the system SHALL remove, weaken, or recompute the ETag for the encoded representation

2.9 WHEN a client sends encoding names in any case THEN the system SHALL normalize them to lowercase before comparison

2.10 WHEN `isBrotliEncoded()` receives brotli-compressed content that decompresses to empty string or `'0'` THEN the system SHALL return true and distinguish decompression failure from valid empty results

#### P2: Test Infrastructure Fixes

2.11 WHEN regression tests run THEN the system SHALL use deterministic incompressible fixtures that remain above the compression threshold and exercise actual guard conditions

2.12 WHEN `ReleaseRepo::script()` executes a child process THEN the system SHALL drain stdout and stderr concurrently or redirect to separate files, and SHALL bound execution time with process cleanup

#### P3: Static Analysis Contract Fix

2.13 WHEN `GuardIndex::declaration()` PHPDoc is evaluated THEN the system SHALL document the `name: ?string` field in the returned array shape

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a response meets all compression criteria and has no existing encoding THEN the system SHALL CONTINUE TO compress it with the negotiated algorithm

3.2 WHEN a client sends `Accept-Encoding: identity` THEN the system SHALL CONTINUE TO return uncompressed content

3.3 WHEN a response body is below the minimum length threshold THEN the system SHALL CONTINUE TO skip compression

3.4 WHEN a response has an incompressible content type THEN the system SHALL CONTINUE TO skip compression

3.5 WHEN a response has an unsuccessful status code (e.g., 404, 500) THEN the system SHALL CONTINUE TO follow configured success-only compression behavior

3.6 WHEN multiple encodings are configured and the client prefers a specific order THEN the system SHALL CONTINUE TO select the best match based on server preference and client acceptance

3.7 WHEN gzip, brotli, or zstd encoders run successfully THEN the system SHALL CONTINUE TO return compressed content with appropriate `Content-Encoding` headers

3.8 WHEN compression reduces content below the original size THEN the system SHALL CONTINUE TO use the compressed version

3.9 WHEN the existing test suite runs against unaffected functionality THEN the system SHALL CONTINUE TO pass all 528 tests with 100% line coverage on src/ files

3.10 WHEN configuration values are loaded from config/response-compression.php THEN the system SHALL CONTINUE TO parse and apply them correctly

3.11 WHEN PHP lint, Rector, and source-only PHPStan analysis run THEN the system SHALL CONTINUE TO pass without errors

3.12 WHEN `isBrotliEncoded()`, `isGzipEncoded()`, or `isZstdEncoded()` receive non-empty valid compressed content THEN the system SHALL CONTINUE TO correctly identify them
