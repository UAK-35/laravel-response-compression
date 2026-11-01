# Implementation Plan

## Overview

This task document implements fixes for 13 bugs identified in the comprehensive audit. The bugs are categorized as:
- **P1 Critical (5 bugs)**: Cache safety and response correctness
- **P2 Functional (7 bugs)**: Functional defects and test infrastructure
- **P3 Static Analysis (1 bug)**: PHPDoc mismatch

Implementation follows the exploratory bugfix methodology:
1. **Explore** - Write tests BEFORE fix to understand bugs (Bug Condition tests)
2. **Preserve** - Write tests for non-buggy behavior (Preservation tests)  
3. **Implement** - Apply fixes in optimal sequence (13 fixes in dependency order)
4. **Validate** - Verify all tests pass and coverage maintained

## Task Dependency Graph

```json
{
  "waves": [
    {
      "wave": 1,
      "description": "Exploration Tests - All independent, can run in parallel",
      "tasks": [
        "1.1", "1.2", "1.3", "1.4", "1.5", "1.6", "1.7", 
        "1.8", "1.9", "1.10", "1.11", "1.12", "1.13"
      ]
    },
    {
      "wave": 2,
      "description": "Preservation Tests - Establish baseline behaviors",
      "tasks": [
        "2.1", "2.2", "2.3", "2.4", "2.5", "2.6", "2.7",
        "2.8", "2.9", "2.10", "2.11", "2.12", "2.13", "2.14"
      ]
    },
    {
      "wave": 3,
      "description": "Simple Independent Fixes - No dependencies",
      "tasks": ["3.1", "3.2", "3.3", "3.6", "3.7", "3.8", "3.13"]
    },
    {
      "wave": 4,
      "description": "Encoder Foundation - Vary header mechanism",
      "tasks": ["3.4"]
    },
    {
      "wave": 5,
      "description": "Encoder Extensions - Build on Vary mechanism",
      "tasks": ["3.5", "3.9", "3.10"]
    },
    {
      "wave": 6,
      "description": "Complex Refactors - Build on multiple-encoding logic",
      "tasks": ["3.11"]
    },
    {
      "wave": 7,
      "description": "Test Infrastructure Updates - Requires bugs fixed",
      "tasks": ["3.12"]
    },
    {
      "wave": 8,
      "description": "Validation - Verify all fixes and preservation",
      "tasks": ["3.14", "3.15", "4"]
    }
  ]
}
```

### Dependency Details

**Wave 1 - Exploration Tests**: All 13 bug condition tests are independent and can be written/run in parallel. Each test demonstrates one specific bug on unfixed code.

**Wave 2 - Preservation Tests**: All 14 preservation tests are independent. They establish baseline behaviors that must remain unchanged.

**Wave 3 - Simple Fixes**: 
- 3.1 (PHPDoc) - Documentation only
- 3.2 (Brotli detection) - Single logic fix
- 3.3 (Case normalization) - Single-line change
- 3.6 (Pre-compression guard) - Independent validation
- 3.7 (Partial response guard) - Independent validation
- 3.8 (Quality zero check) - Independent validation
- 3.13 (Deadlock fix) - Test infrastructure, independent

**Wave 4 - Encoder Foundation**:
- 3.4 (Vary header) - Establishes setVary() pattern in all three encoders

**Wave 5 - Encoder Extensions** (depends on Wave 4):
- 3.5 (ETag handling) - Builds on encoder structure from 3.4
- 3.9 (Loop break) - Uses Vary-fixed encoders from 3.4
- 3.10 (User-Agent Vary) - Uses setVary() mechanism from 3.4

**Wave 6 - Complex Refactor** (depends on Waves 4-5):
- 3.11 (Fallback gating) - Refactors handle() with multiple-encoding logic, needs Vary and loop fixes stable

**Wave 7 - Test Updates** (depends on Waves 3-6):
- 3.12 (Test fixtures) - Updates tests to use incompressible fixtures, needs bugs 1-10 fixed to validate properly

**Wave 8 - Final Validation**:
- 3.14 - Verify exploration tests now pass
- 3.15 - Verify preservation tests still pass
- 4 - Final checkpoint with all gates

## Tasks

---

## Phase 1: Bug Condition Exploration Tests

These tests demonstrate each bug exists on UNFIXED code. Each test will FAIL initially, confirming the bug. After implementation, they will PASS, confirming the fix works.

- [x] 1. Write bug condition exploration tests (BEFORE implementing fixes)
  - **Property 1: Bug Condition 1** - Vary Header Overwrite
  - **Property 2: Bug Condition 2** - Multiple Encoding Loop
  - **Property 3: Bug Condition 3** - Pre-Compression Detection
  - **Property 4: Bug Condition 4** - Quality Zero Handling
  - **Property 5: Bug Condition 5** - Partial Response Handling
  - **Property 6: Bug Condition 6** - Fallback Gating
  - **Property 7: Bug Condition 7** - User-Agent Variation
  - **Property 8: Bug Condition 8** - ETag Handling
  - **Property 9: Bug Condition 9** - Case Normalization
  - **Property 10: Bug Condition 10** - Brotli Detection
  - **Property 11: Bug Condition 11** - Test Fixtures
  - **Property 12: Bug Condition 12** - Deadlock Prevention
  - **Property 13: Bug Condition 13** - PHPDoc Accuracy
  - **IMPORTANT**: Write these tests BEFORE implementing any fixes
  - **CRITICAL**: These tests MUST FAIL on unfixed code - failure confirms bugs exist
  - **DO NOT attempt to fix the tests or the code when they fail**
  - **GOAL**: Surface counterexamples demonstrating each bug exists
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: All 13 tests FAIL (this is correct - it proves bugs exist)
  - Document counterexamples found to understand root causes
  - Mark task complete when tests are written, run, and failures are documented
  - _Requirements: 1.1-1.13, 2.1-2.13_

  - [x] 1.1 Bug 1 - Vary Header Overwrite exploration test
    - **Property 1: Bug Condition 1** - Vary Header Overwrite
    - Create response with `Vary: Origin, Accept-Language`
    - Apply gzip compression via middleware
    - Assert `Vary` header contains both original values AND `Accept-Encoding`
    - **EXPECTED ON UNFIXED CODE**: FAILS - Vary header shows only `Accept-Encoding`, losing `Origin, Accept-Language`
    - This proves Bug 1 exists (headers->add() replaces instead of appending)
    - _Requirements: 1.1, 2.1_

  - [x] 1.2 Bug 2 - Multiple Encoding Loop exploration test
    - **Property 2: Bug Condition 2** - Multiple Encoding Loop
    - Configure `try_multiple_encodings=true`, `encodingOrder=['gzip', 'br']`
    - Create incompressible 20KB content (base64 random data that stays above min_length after first compression)
    - Send request with `Accept-Encoding: gzip, br`
    - Decompress response and count encoding layers applied
    - Assert response has SINGLE encoding in Content-Encoding header
    - Assert decompressing once recovers original content
    - **EXPECTED ON UNFIXED CODE**: FAILS - Content-Encoding shows only `br` but content is double-compressed (gzip then brotli)
    - This proves Bug 2 exists (loop has no break statement)
    - _Requirements: 1.2, 2.2_

  - [x] 1.3 Bug 3 - Pre-Compression Detection exploration test
    - **Property 3: Bug Condition 3** - Pre-Compression Detection
    - Create 20KB incompressible content
    - Manually gzip compress it: `$gzipped = gzencode($content, 9)`
    - Create response with body=$gzipped, `Content-Encoding: gzip`
    - Pass through middleware
    - Decompress result once with `gzdecode()`
    - Assert single decompression recovers original content
    - **EXPECTED ON UNFIXED CODE**: FAILS - Response is double-gzipped, single decompress fails
    - This proves Bug 3 exists (validateResponse doesn't check Content-Encoding header)
    - _Requirements: 1.3, 2.3_

  - [x] 1.4 Bug 4 - Quality Zero Handling exploration test
    - **Property 4: Bug Condition 4** - Quality Zero Handling
    - Configure `algorithm=gzip`
    - Send request with `Accept-Encoding: gzip;q=0` (explicit refusal)
    - Assert response has NO Content-Encoding header
    - Assert response body is uncompressed
    - **EXPECTED ON UNFIXED CODE**: FAILS - Response is gzipped despite q=0
    - Also test wildcard patterns: `*;q=0`, `*;q=1, gzip;q=0`
    - This proves Bug 4 exists (quality values not checked)
    - _Requirements: 1.4, 2.4_

  - [x] 1.5 Bug 5 - Partial Response Handling exploration test
    - **Property 5: Bug Condition 5** - Partial Response Handling
    - Create 20KB content
    - Create 206 response with `Content-Range: bytes 0-10999/20000` and 11KB body subset
    - Pass through middleware with `Accept-Encoding: gzip`
    - Assert Content-Range header unchanged
    - Assert body size matches Content-Range size (11KB, not compressed)
    - **EXPECTED ON UNFIXED CODE**: FAILS - Body is compressed to ~73 bytes but Content-Range still shows 11KB
    - This proves Bug 5 exists (validateResponse doesn't check for 206 or Content-Range)
    - _Requirements: 1.5, 2.5_

  - [x] 1.6 Bug 6 - Fallback Gating exploration test
    - **Property 6: Bug Condition 6** - Fallback Gating
    - Configure `algorithm=br`, `try_multiple_encodings=true`, `encodingOrder=['gzip', 'br']`
    - Send request with `Accept-Encoding: gzip` (accepts gzip but NOT br)
    - Assert response has `Content-Encoding: gzip`
    - Assert response body is gzip-compressed
    - **EXPECTED ON UNFIXED CODE**: FAILS - Response is uncompressed (validateRequest blocks on algorithm=br mismatch)
    - This proves Bug 6 exists (fallback gated by single algorithm check)
    - _Requirements: 2.1, 2.6_

  - [x] 1.7 Bug 7 - User-Agent Variation exploration test
    - **Property 7: Bug Condition 7** - User-Agent Variation
    - Configure brotli with `nonSupportingUserAgentPrefixes=['axios']`
    - Send two requests both with `Accept-Encoding: br`:
      - Request A: `User-Agent: axios/1.7.2` (should be refused brotli)
      - Request B: `User-Agent: Mozilla/5.0` (should get brotli)
    - Assert BOTH responses have `Vary: Accept-Encoding, User-Agent`
    - **EXPECTED ON UNFIXED CODE**: FAILS - Both responses have only `Vary: Accept-Encoding`
    - This proves Bug 7 exists (User-Agent not added to Vary despite influencing decision)
    - _Requirements: 2.2, 2.7_

  - [x] 1.8 Bug 8 - ETag Handling exploration test
    - **Property 8: Bug Condition 8** - ETag Handling
    - Create 11KB response with `ETag: "original-tag"` (strong ETag - no W/ prefix)
    - Pass through middleware with gzip compression
    - Assert ETag is either:
      - Removed (no ETag header), OR
      - Weakened (starts with `W/`), OR
      - Changed value
    - **EXPECTED ON UNFIXED CODE**: FAILS - ETag remains `"original-tag"` despite body changing
    - This proves Bug 8 exists (encoders don't handle strong ETags)
    - _Requirements: 2.3, 2.8_

  - [x] 1.9 Bug 9 - Case Normalization exploration test
    - **Property 9: Bug Condition 9** - Case Normalization
    - Configure `algorithm=gzip`
    - Send request with `Accept-Encoding: GZIP` (uppercase)
    - Assert response has `Content-Encoding: gzip`
    - Assert response body is compressed
    - **EXPECTED ON UNFIXED CODE**: FAILS - Response is uncompressed (case-sensitive match fails)
    - Also test mixed case: `Gzip`, `GZip`
    - This proves Bug 9 exists (encoding comparison is case-sensitive)
    - _Requirements: 2.4, 2.9_

  - [x] 1.10 Bug 10 - Brotli Detection exploration test
    - **Property 10: Bug Condition 10** - Brotli Detection
    - Test valid compressed empty string: `$content = brotli_compress('')`
    - Assert `Enc::isBrotliEncoded($content)` returns `true`
    - Test valid compressed zero string: `$content = brotli_compress('0')`
    - Assert `Enc::isBrotliEncoded($content)` returns `true`
    - Verify `brotli_uncompress($content)` succeeds for both
    - **EXPECTED ON UNFIXED CODE**: FAILS - Both return `false` despite successful decompression
    - This proves Bug 10 exists (function rejects valid empty/zero results)
    - _Requirements: 2.5, 2.10_

  - [x] 1.11 Bug 11 - Test Fixtures exploration test
    - **Property 11: Bug Condition 11** - Test Fixtures
    - Examine existing pre-compressed test fixture
    - Compress the fixture: `$compressed = gzencode($fixture, 9)`
    - Assert `strlen($compressed) > 1024` (must exceed min_length)
    - **EXPECTED ON UNFIXED CODE**: FAILS - Compressed fixture is < 1024 bytes
    - Examine first-candidate test: assert it offers multiple accepted encodings
    - Examine vary-preservation test: assert it requests compression (not identity)
    - This proves Bug 11 exists (tests don't exercise claimed behaviors)
    - _Requirements: 2.6, 2.11_

  - [x] 1.12 Bug 12 - Deadlock Prevention exploration test
    - **Property 12: Bug Condition 12** - Deadlock Prevention
    - Create test script that writes 1MB to stderr before stdout
    - Call `ReleaseRepo::script()` with 5-second timeout
    - Assert execution completes within timeout
    - Assert both stdout and stderr are captured
    - **EXPECTED ON UNFIXED CODE**: FAILS - Test times out (deadlock on stderr pipe)
    - This proves Bug 12 exists (parent blocks waiting for stdout while child blocks on stderr)
    - _Requirements: 2.7, 2.12_

  - [x] 1.13 Bug 13 - PHPDoc Accuracy exploration test
    - **Property 13: Bug Condition 13** - PHPDoc Accuracy
    - Run PHPStan analysis on code accessing `GuardIndex::declaration()` return value
    - Access the `name` field: `$declaration['name']`
    - **EXPECTED ON UNFIXED CODE**: PHPStan reports undefined offset error on 'name'
    - Check actual return value includes `name: ?string` field
    - This proves Bug 13 exists (PHPDoc incomplete)
    - _Requirements: 3.1, 2.13_

---

## Phase 2: Preservation Property Tests

These tests verify non-buggy behavior is unchanged. Write BEFORE implementing fixes. Tests must PASS on UNFIXED code.

- [x] 2. Write preservation property tests (BEFORE implementing fixes)
  - **Property 14: Preservation** - Normal Compression Path
  - **Property 15: Preservation** - Configuration Behavior
  - **Property 16: Preservation** - Test Coverage
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for non-buggy inputs
  - Write property-based tests capturing observed behavior patterns
  - Property-based testing generates many test cases for stronger guarantees
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1-3.12_

  - [x] 2.1 Preservation - Normal Vary header addition
    - **Property 14: Preservation** - Normal Vary Addition
    - Test responses WITHOUT existing Vary headers
    - Observe: After compression, response has `Vary: Accept-Encoding`
    - Write property test: for all responses with no Vary header, compression adds only `Vary: Accept-Encoding`
    - Run on UNFIXED code - **MUST PASS** (baseline to preserve)
    - _Requirements: 3.1.1_

  - [x] 2.2 Preservation - Single compression application
    - **Property 14: Preservation** - Single Compression
    - Test compressible content on first try (e.g., 11KB repeated 'A')
    - Observe: Single gzip applied, decompresses in one pass
    - Write property test: for all easily compressible content, single encoding applied
    - Run on UNFIXED code - **MUST PASS**
    - _Requirements: 3.1.2_

  - [x] 2.3 Preservation - No existing encoding path
    - **Property 14: Preservation** - Fresh Content
    - Test responses without Content-Encoding header
    - Observe: Compression applied normally
    - Write property test: for all responses without Content-Encoding, compression proceeds
    - Run on UNFIXED code - **MUST PASS**
    - _Requirements: 3.1.3_

  - [x] 2.4 Preservation - Positive quality values
    - **Property 14: Preservation** - Accepted Encodings
    - Test requests with `Accept-Encoding: gzip` or `gzip;q=0.8`
    - Observe: Compression applied
    - Write property test: for all positive quality values, compression proceeds
    - Run on UNFIXED code - **MUST PASS**
    - _Requirements: 3.1.4_

  - [x] 2.5 Preservation - Status 200 responses
    - **Property 14: Preservation** - Complete Responses
    - Test successful 200 responses without Content-Range
    - Observe: Compression produces correct encoded content
    - Write property test: for all complete responses, compression succeeds
    - Run on UNFIXED code - **MUST PASS**
    - _Requirements: 3.1.5_

  - [x] 2.6 Preservation - Single-encoding mode
    - **Property 15: Preservation** - Single Algorithm
    - Test with `try_multiple_encodings=false`, `algorithm=gzip`, `Accept-Encoding: gzip`
    - Observe: Gzip compression applied
    - Write property test: single-mode with matching algorithm compresses correctly
    - Run on UNFIXED code - **MUST PASS**
    - _Requirements: 3.2.1_

  - [x] 2.7 Preservation - Empty User-Agent skip lists
    - **Property 15: Preservation** - No UA Filtering
    - Test with empty `nonSupportingUserAgentPrefixes`
    - Observe: Compression based solely on Accept-Encoding
    - Write property test: empty skip lists don't affect compression
    - Run on UNFIXED code - **MUST PASS**
    - _Requirements: 3.2.2_

  - [x] 2.8 Preservation - Weak ETag and no ETag
    - **Property 15: Preservation** - ETag Preservation
    - Test responses with no ETag or weak ETag `W/"tag"`
    - Observe: Current handling preserved
    - Write property test: weak/absent ETags handled as currently implemented
    - Run on UNFIXED code - **MUST PASS**
    - _Requirements: 3.2.3_

  - [x] 2.9 Preservation - Lowercase encoding names
    - **Property 15: Preservation** - Standard Case
    - Test `Accept-Encoding: gzip, br` (lowercase)
    - Observe: Correct matching and compression
    - Write property test: lowercase encodings continue to match
    - Run on UNFIXED code - **MUST PASS**
    - _Requirements: 3.2.4_

  - [x] 2.10 Preservation - Non-empty brotli content
    - **Property 15: Preservation** - Standard Brotli
    - Test `brotli_compress('hello world')`
    - Observe: `isBrotliEncoded()` returns true
    - Write property test: normal brotli content detected correctly
    - Run on UNFIXED code - **MUST PASS**
    - _Requirements: 3.2.5_

  - [x] 2.11 Preservation - Existing validation checks
    - **Property 15: Preservation** - Skip Conditions
    - Test: wrong content type, status not successful (excluding 206), body too small, streamed response
    - Observe: Compression skipped per existing logic
    - Write property test: all existing skip conditions continue to work
    - Run on UNFIXED code - **MUST PASS**
    - _Requirements: 3.3.1, 3.3.2_

  - [x] 2.12 Preservation - Identity encoding
    - **Property 15: Preservation** - Identity Requests
    - Test `Accept-Encoding: identity` or no Accept-Encoding header
    - Observe: `prefersIdentity()` logic handles correctly
    - Write property test: identity preferences continue to skip compression
    - Run on UNFIXED code - **MUST PASS**
    - _Requirements: 3.3.3_

  - [x] 2.13 Preservation - Configuration validation
    - **Property 15: Preservation** - Config Parsing
    - Test all config keys from `config/response-compression.php`
    - Observe: Config::get() validates and provides access
    - Write property test: configuration continues to parse correctly
    - Run on UNFIXED code - **MUST PASS**
    - _Requirements: 3.4.1-3.4.6_

  - [x] 2.14 Preservation - Gate compliance
    - **Property 16: Preservation** - Quality Gates
    - Run full gate: `composer checks -- --require-all`
    - Observe: All 11 checks pass on unfixed code
    - Document current state as baseline
    - After fixes, gate must continue to pass
    - Run on UNFIXED code - **MUST PASS**
    - _Requirements: 3.3.5, 3.3.6, 3.3.7_

---

## Phase 3: Implementation

Apply fixes in optimal sequence. Each fix is minimal and surgical, preserving existing architecture.

- [ ] 3. Fix for all 13 bugs (implement in this sequence)

  - [x] 3.1 Bug 13 - PHPDoc Accuracy (trivial documentation fix)
    - Update `tests/Support/GuardIndex.php` line 1388 PHPDoc
    - Change from: `@return array{section: string, line: int}|null`
    - Change to: `@return array{section: string, line: int, name: ?string}|null`
    - Run PHPStan on tests/ - should show zero errors on declaration() callers
    - _Bug_Condition: GuardIndex::declaration() returns name field undocumented in PHPDoc_
    - _Expected_Behavior: PHPDoc documents all fields including name: ?string_
    - _Preservation: Other GuardIndex methods unchanged_
    - _Requirements: 3.1, 2.13_

  - [x] 3.2 Bug 10 - Brotli Detection (simple logic fix)
    - Update `src/Support/Enc.php` lines 24-26 in `isBrotliEncoded()`
    - Change from: `return $decoded !== false && $decoded !== '' && $decoded !== '0';`
    - Change to: `return $decoded !== false;`
    - Remove rejection of empty string and '0' - these are valid decompression results
    - Test with `brotli_compress('')` and `brotli_compress('0')` - should return true
    - _Bug_Condition: Valid compressed empty/zero strings return false_
    - _Expected_Behavior: Any successful decompression (not false) returns true_
    - _Preservation: Non-empty brotli content detection unchanged_
    - _Requirements: 2.5, 2.10_

  - [x] 3.3 Bug 9 - Case Normalization (single-line fix)
    - Update `src/Middleware/CompressResponse.php` line 267 in `validateRequestPerAlgoAndUserAgent()`
    - Change from: `if (! in_array($compressionAlgorithm, $request->getEncodings(), true)) {`
    - Change to: `$requestEncodings = array_map('strtolower', $request->getEncodings());` then `if (! in_array(strtolower($compressionAlgorithm), $requestEncodings, true)) {`
    - Normalize both sides to lowercase before comparison
    - Test with `Accept-Encoding: GZIP, Gzip, GZip` - all should match
    - _Bug_Condition: Uppercase encoding names fail to match lowercase config_
    - _Expected_Behavior: Case-insensitive matching succeeds_
    - _Preservation: Lowercase encoding names continue to match_
    - _Requirements: 2.4, 2.9_

  - [x] 3.4 Bug 1 - Vary Header Preservation (encoder changes)
    - Update all three encoders: `src/Encoders/GzipEncoder.php`, `BrotliEncoder.php`, `ZstdEncoder.php`
    - In each encoder's `handle()` method (lines 20-24 GzipEncoder, 22-26 others):
    - Replace `$response->headers->add(['Vary' => 'Accept-Encoding', ...])` with separate set calls
    - Use: `$response->setVary('Accept-Encoding', false);` (false = append mode)
    - Structure: set Content-Encoding, set Content-Length, then setVary
    - Test response with `Vary: Origin` - after compression should have `Vary: Origin, Accept-Encoding`
    - _Bug_Condition: headers->add() replaces existing Vary values_
    - _Expected_Behavior: setVary(..., false) appends to existing values_
    - _Preservation: Responses without Vary still get Vary: Accept-Encoding_
    - _Requirements: 1.1, 2.1_

  - [x] 3.5 Bug 8 - ETag Handling (builds on Bug 1 encoder changes)
    - Update all three encoders after setContent() call
    - Add ETag handling logic before setting headers:
    ```php
    if ($response->headers->has('ETag')) {
        $etag = $response->headers->get('ETag');
        if ($etag && !str_starts_with($etag, 'W/') && str_starts_with($etag, '"')) {
            $response->headers->set('ETag', 'W/' . $etag);
        }
    }
    ```
    - Weaken strong ETags (add W/ prefix) after compression changes representation
    - Test strong ETag `"abc"` - should become `W/"abc"` after compression
    - Test weak ETag `W/"abc"` - should remain unchanged
    - _Bug_Condition: Strong ETags unchanged despite byte representation changing_
    - _Expected_Behavior: Strong ETags weakened to W/ prefix_
    - _Preservation: Weak ETags and absent ETags handled as before_
    - _Requirements: 2.3, 2.8_

  - [x] 3.6 Bug 3 - Pre-Compression Detection (validation guard)
    - Update `src/Middleware/CompressResponse.php` in `validateResponse()` method
    - Add check after line 162 (after status validation):
    ```php
    if ($response->headers->has('Content-Encoding')) {
        $this->logDebugStatus(
            Diagnostic::ResponseSkipped,
            ['reason' => 'already_encoded', 'uri' => $requestUri]
        );
        return false;
    }
    ```
    - Check for any Content-Encoding header presence before compression
    - Test response with `Content-Encoding: gzip` - should skip compression
    - _Bug_Condition: Responses with existing Content-Encoding get compressed again_
    - _Expected_Behavior: Any Content-Encoding header causes compression skip_
    - _Preservation: Responses without Content-Encoding compress normally_
    - _Requirements: 1.3, 2.3_

  - [x] 3.7 Bug 5 - Partial Response Handling (validation guard)
    - Update `src/Middleware/CompressResponse.php` in `validateResponse()` method
    - Add check after existing 304 check (after line 170):
    ```php
    if ($response->getStatusCode() === 206 || $response->headers->has('Content-Range')) {
        $this->logDebugStatus(
            Diagnostic::ResponseSkipped,
            ['reason' => 'partial_content', 'uri' => $requestUri]
        );
        return false;
    }
    ```
    - Skip compression for any 206 status or Content-Range header
    - Test 206 response with Content-Range - should skip compression
    - _Bug_Condition: Partial responses compressed, invalidating byte ranges_
    - _Expected_Behavior: Status 206 or Content-Range causes compression skip_
    - _Preservation: Status 200 complete responses compress normally_
    - _Requirements: 1.5, 2.5_

  - [x] 3.8 Bug 4 - Quality Zero Handling (validation logic)
    - Update `src/Middleware/CompressResponse.php` in `validateRequestPerAlgoAndUserAgent()`
    - Add quality check after line 267 (after encoding presence check):
    ```php
    $acceptEncodingHeader = $request->headers->get('Accept-Encoding');
    $preferences = AcceptEncoding::preferences($acceptEncodingHeader);
    $quality = $preferences[$compressionAlgorithm] 
        ?? $preferences[AcceptEncoding::ANY] 
        ?? 1.0;
    
    if ($quality <= 0.0) {
        $this->logDebugStatus(
            Diagnostic::RequestRefused,
            ['encoding' => $compressionAlgorithm, 'quality' => $quality, 'uri' => $requestUri]
        );
        return false;
    }
    ```
    - Check quality value, refuse if zero or negative
    - Test `gzip;q=0`, `*;q=0`, `*;q=1, gzip;q=0` - all should skip gzip
    - _Bug_Condition: Quality q=0 ignored, refused encodings still applied_
    - _Expected_Behavior: Quality <= 0 causes encoding refusal_
    - _Preservation: Positive quality values continue to compress_
    - _Requirements: 1.4, 2.4_

  - [x] 3.9 Bug 2 - Multiple Encoding Loop (control flow fix)
    - Update `src/Middleware/CompressResponse.php` lines 62-69 in `handle()`
    - Add `break;` statement after successful compression in loop:
    ```php
    if ($this->tryMultipleEncodings()) {
        foreach ($this->encodingOrder() as $encoding) {
            if ($this->shouldCompressForAlgo($encoding, $request, $response, $requestUri)) {
                $response = $this->encode($encoding, $response, $requestUri);
                break; // Stop after first successful compression
            }
        }
        return $this->vary($response);
    }
    ```
    - Stop iteration after first successful compression
    - Test incompressible content with multiple accepted encodings - should apply only first
    - _Bug_Condition: Loop continues after first compression, applying multiple encodings_
    - _Expected_Behavior: First successful compression stops loop_
    - _Preservation: Single successful compression produces same output_
    - _Requirements: 1.2, 2.2_

  - [ ] 3.10 Bug 7 - User-Agent Variation (tracking and header)
    - Update `src/Middleware/CompressResponse.php`:
    - Add property after line 38: `private bool $userAgentConsulted = false;`
    - In `validateRequestPerAlgoAndUserAgent()` after line 274, set flag when checking User-Agent:
    ```php
    if ($this->nonSupportingUserAgentPrefixes($compressionAlgorithm)) {
        $this->userAgentConsulted = true;
        // ... existing user-agent check ...
    }
    ```
    - In `vary()` method after line 116, conditionally add User-Agent:
    ```php
    if ($this->userAgentConsulted) {
        $response->setVary('User-Agent', false);
    }
    ```
    - Test with User-Agent skip lists - should add User-Agent to Vary
    - _Bug_Condition: User-Agent influences decision but not in Vary header_
    - _Expected_Behavior: User-Agent added to Vary when consulted_
    - _Preservation: Empty skip lists don't add User-Agent to Vary_
    - _Requirements: 2.2, 2.7_

  - [ ] 3.11 Bug 6 - Fallback Gating (handle() refactor)
    - Update `src/Middleware/CompressResponse.php` `handle()` method (lines 40-78)
    - In multiple-encoding mode, validate against candidate list directly:
    ```php
    if ($this->tryMultipleEncodings()) {
        $hasAcceptedEncoding = false;
        foreach ($this->encodingOrder() as $encoding) {
            if (in_array($encoding, $request->getEncodings(), true)) {
                $hasAcceptedEncoding = true;
                break;
            }
        }
        
        if (! $hasAcceptedEncoding) {
            $this->logDebugStatus(Diagnostic::CompressionNotAllowed);
            return $this->vary($next($request));
        }
    } else {
        // Single-algorithm mode uses existing validateRequest
        if (! $this->validateRequest($request, $requestUri)) {
            $this->logDebugStatus(Diagnostic::CompressionNotAllowed);
            return $this->vary($next($request));
        }
    }
    ```
    - Check acceptance before response, negotiate against full candidate list
    - Test `algorithm=br` with `Accept-Encoding: gzip` in multiple mode - should compress with gzip
    - _Bug_Condition: Single algorithm check blocks fallback in multiple-encoding mode_
    - _Expected_Behavior: Multiple mode negotiates against candidate list directly_
    - _Preservation: Single-encoding mode with matching algorithm unchanged_
    - _Requirements: 2.1, 2.6_

  - [ ] 3.12 Bug 11 - Test Fixtures (update test infrastructure)
    - Add incompressible fixture generator in `tests/Pest.php`:
    ```php
    function incompressibleContent(int $bytes = 2048): string
    {
        $seed = 'ResponseCompressionTestFixtureSeed';
        $hash = hash('sha256', $seed);
        $pattern = base64_encode(hex2bin($hash));
        
        return str_repeat($pattern, (int) ceil($bytes / strlen($pattern)))
            . substr($pattern, 0, $bytes % strlen($pattern));
    }
    ```
    - Update pre-compressed test in `tests/Unit/Middleware/CompressResponseTest.php`:
      - Use `incompressibleContent(3000)` as fixture
      - Assert `strlen(gzencode($content, 9)) > 1024` before test
    - Update first-candidate test:
      - Configure to accept multiple encodings: `Accept-Encoding: gzip, br`
      - Configure order to prefer different algorithm than primary
    - Update vary-preservation test:
      - Change to request gzip compression (not identity)
      - Use response with existing Vary header
    - All three tests should now exercise their claimed behaviors
    - _Bug_Condition: Tests pass without exercising promised behaviors_
    - _Expected_Behavior: Tests use fixtures that actually trigger guard conditions_
    - _Preservation: Tests exercising real conditions continue to pass_
    - _Requirements: 2.6, 2.11_

  - [ ] 3.13 Bug 12 - Deadlock Prevention (pipe draining)
    - Update `tests/Support/ReleaseRepo.php` `script()` method (lines 416-431)
    - Replace sequential pipe reading with concurrent draining:
    ```php
    private function drainPipes(array $pipes): array
    {
        $stdout = '';
        $stderr = '';
        $timeout = 30;
        $endTime = time() + $timeout;
        
        while (!feof($pipes[1]) || !feof($pipes[2])) {
            if (time() > $endTime) {
                throw new RuntimeException('Process execution timeout');
            }
            
            $read = array_filter([$pipes[1], $pipes[2]], fn($p) => is_resource($p) && !feof($p));
            if (empty($read)) break;
            
            $write = $except = null;
            if (stream_select($read, $write, $except, 1) > 0) {
                foreach ($read as $pipe) {
                    $chunk = fread($pipe, 8192);
                    if ($chunk !== false) {
                        $pipe === $pipes[1] ? $stdout .= $chunk : $stderr .= $chunk;
                    }
                }
            }
        }
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [$stdout, $stderr];
    }
    ```
    - Use `stream_select()` to read from whichever pipe has data
    - Add 30-second timeout with process cleanup
    - Test with child writing 1MB to stderr - should complete without deadlock
    - _Bug_Condition: Sequential pipe reading deadlocks on large stderr output_
    - _Expected_Behavior: Concurrent draining with timeout prevents deadlock_
    - _Preservation: Normal output volumes continue to work_
    - _Requirements: 2.7, 2.12_

  - [ ] 3.14 Verify bug condition exploration tests now pass
    - **Property 1-13: Expected Behavior** - All 13 bug fixes validated
    - **IMPORTANT**: Re-run the SAME tests from Phase 1 - do NOT write new tests
    - The tests from Phase 1 encode the expected behavior
    - When these tests pass, it confirms all bugs are fixed
    - Run all 13 bug condition tests from Phase 1
    - **EXPECTED OUTCOME**: All tests PASS (confirms bugs are fixed)
    - _Requirements: 2.1-2.13_

  - [ ] 3.15 Verify preservation tests still pass
    - **Property 14-16: Preservation** - All preservation guarantees validated
    - **IMPORTANT**: Re-run the SAME tests from Phase 2 - do NOT write new tests
    - Run all 14 preservation tests from Phase 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Verify 100% line coverage maintained on all src/ files
    - Verify all bin/ coverage floors still pass
    - _Requirements: 3.1-3.12_

- [ ] 4. Checkpoint - Ensure all tests pass and gates green
  - Run full test suite: `composer test:unit` - all 528+ tests should pass
  - Run full gate: `composer checks -- --require-all` - all 11 checks green
  - Verify coverage: 100% line coverage on all src/ files maintained
  - Run PHPStan max level on src/ only: zero diagnostics
  - Run agentic verification: `python .agents/verify.py` - zero findings
  - Document: Any new tests added, coverage maintained, all fixes complete
  - Ask user if questions arise
  - _Requirements: All requirements validated_


---

## Notes

### Implementation Strategy

This task list follows the bug condition methodology optimized for 13 distinct bugs:

1. **Exploration Phase**: Write tests demonstrating all 13 bugs BEFORE any fixes. These tests will fail on unfixed code, confirming each bug exists and documenting its counterexamples.

2. **Preservation Phase**: Write property-based tests capturing non-buggy behavior patterns. These tests pass on unfixed code, establishing the baseline that must be preserved.

3. **Sequential Implementation**: Apply fixes in dependency order (3.1→3.13) to maintain a green test suite throughout. Order chosen to:
   - Start with trivial changes (PHPDoc, detection logic)
   - Establish encoder patterns early (Vary, ETag)
   - Build validation guards (pre-compression, partial, quality)
   - Complete complex refactors last (fallback gating)
   - Update test infrastructure at end (fixtures, deadlock)

4. **Continuous Validation**: After each fix, relevant exploration tests transition from FAIL to PASS. All preservation tests must remain PASS throughout.

### Key Constraints

- **100% Line Coverage**: All `src/` files must maintain 100.0% coverage
- **Gate Compliance**: All 11 checks in `composer checks -- --require-all` must pass
- **PHPStan Max**: Zero diagnostics on `src/` at maximum strictness level
- **Test Count**: 528+ tests must pass (all existing + new bug condition tests)
- **Public API**: No breaking changes to middleware, encoders, or config structure
- **Agentic Layer**: `python .agents/verify.py` must report zero findings

### Testing Approach

**Property-Based Testing Recommended**: The preservation requirements cover a large input domain (all non-buggy request/response combinations). Property-based testing using libraries like Pest's property testing features can:
- Generate hundreds of test cases automatically
- Catch edge cases manual tests miss
- Provide mathematical guarantees about preservation
- Efficiently validate all 3.1-3.12 requirements simultaneously

**Deterministic Fixtures**: Bug 11 fix introduces `incompressibleContent()` generator using fixed seed for reproducible, incompressible test data. This ensures tests consistently exercise guards across platforms and runs.

### File Changes Summary

**Source Files Modified** (13 bugs across 7 files):
- `src/Middleware/CompressResponse.php` - Bugs 2, 3, 4, 5, 6, 7, 9
- `src/Encoders/GzipEncoder.php` - Bugs 1, 8
- `src/Encoders/BrotliEncoder.php` - Bugs 1, 8
- `src/Encoders/ZstdEncoder.php` - Bugs 1, 8
- `src/Support/Enc.php` - Bug 10

**Test Files Modified**:
- `tests/Support/GuardIndex.php` - Bug 13
- `tests/Support/ReleaseRepo.php` - Bug 12
- `tests/Pest.php` - Bug 11 (fixture generator)
- `tests/Unit/Middleware/CompressResponseTest.php` - Bug 11 (test updates)

**No New Files**: All fixes work within existing architecture

### Coverage Verification

After all fixes:
```bash
composer test:unit           # Must show 100% on all src/ files
composer checks -- --require-all  # All 11 gate checks green
"$(python .agents/render_local.py --value __PHP_EXE__)" bin/coverage.php  # Verify floors
python .agents/verify.py     # Zero findings
```

### Release Impact

These are bugfixes with no breaking changes:
- **Semver**: Patch version (e.g., 1.2.3 → 1.2.4)
- **Changelog**: 13 bullets under "### Fixed" in CHANGELOG.md
- **Migration**: No consumer action required (transparent fixes)
- **Risk**: Low - all preservation tests validate non-buggy paths unchanged
