# All 13 Bugs Bugfix Design

## Overview

This design addresses 13 bugs identified in the comprehensive audit of the Laravel Response Compression package. The bugs span three categories: P1 critical runtime and cache safety issues (5 bugs), P2 functional defects and test infrastructure issues (7 bugs), and P3 static analysis contract defects (1 bug).

The fix strategy follows a minimal, surgical approach: each bug is addressed at its root cause without introducing architectural changes. The fixes preserve 100% line coverage on all `src/` files, maintain the existing public API, and follow the package's pipeline architecture (validate request → validate response → select encoding → compress → set headers).

All fixes are designed to be testable with deterministic fixtures and must pass the full gate including PHPStan max level on `src/`.

## Glossary

- **Bug_Condition (C)**: The specific conditions under which each of the 13 bugs manifests
- **Property (P)**: The expected correct behavior for each bug condition
- **Preservation**: Existing correct behavior that must remain unchanged across all fixes
- **CompressResponse**: The middleware class in `src/Middleware/CompressResponse.php` orchestrating the compression pipeline
- **Encoder**: The interface implemented by `GzipEncoder`, `BrotliEncoder`, and `ZstdEncoder` that performs actual compression
- **Vary header**: HTTP cache variation marker indicating which request headers affect response content
- **Content-Encoding**: HTTP header declaring which encoding transformations have been applied to the response body
- **Accept-Encoding**: HTTP request header declaring which encodings the client accepts, with optional quality values
- **Quality value (q)**: A 0.0-1.0 preference weight in Accept-Encoding; q=0 is explicit refusal
- **Strong ETag**: A quoted entity tag validator indicating byte-for-byte representation identity
- **Weak ETag**: An ETag prefixed with `W/` indicating semantic equivalence despite byte differences
- **HTTP 206**: Partial Content response status with Content-Range describing byte subset of full representation
- **AcceptEncoding**: The parser class in `src/Support/AcceptEncoding.php` extracting encoding preferences from Accept-Encoding header
- **Enc**: The detection helper class in `src/Support/Enc.php` verifying whether content is already compressed
- **GuardIndex**: The test support class in `tests/Support/GuardIndex.php` reading guard documentation structure
- **ReleaseRepo**: The test helper class in `tests/Support/ReleaseRepo.php` executing git operations in isolated repositories

## Bug Details

### Bug Condition

The 13 bugs manifest under the following conditions:

**P1: Critical Runtime and Cache Safety Issues**

**Bug 1 - Vary Header Overwrite:**
```
FUNCTION isBugCondition_1(input)
  INPUT: input of type {response: Response, originalVaryHeaders: string[]}
  OUTPUT: boolean
  
  RETURN input.response.hasHeader('Vary')
         AND length(input.originalVaryHeaders) > 0
         AND encoderRunsCompression(input.response)
         AND afterCompression(input.response.headers.get('Vary')) != input.originalVaryHeaders
END FUNCTION
```

**Bug 2 - Multiple Encoding Loop:**
```
FUNCTION isBugCondition_2(input)
  INPUT: input of type {request: Request, response: Response, config: Config}
  OUTPUT: boolean
  
  RETURN input.config.try_multiple_encodings == true
         AND count(acceptedEncodings(input.request)) >= 2
         AND firstCompressionRemainOverMinLength(input.response)
         AND responseHasMultipleEncodingsApplied(input.response)
         AND responseAdvertisesOnlyLastEncoding(input.response)
END FUNCTION
```

**Bug 3 - Pre-Compression Detection:**
```
FUNCTION isBugCondition_3(input)
  INPUT: input of type {response: Response}
  OUTPUT: boolean
  
  RETURN input.response.hasHeader('Content-Encoding')
         AND input.response.getHeader('Content-Encoding') IN ['gzip', 'br', 'zstd']
         AND input.response.content.isAlreadyCompressed()
         AND middlewareCompressesAgain(input.response)
END FUNCTION
```

**Bug 4 - Quality Zero Handling:**
```
FUNCTION isBugCondition_4(input)
  INPUT: input of type {request: Request, algorithmOffered: string}
  OUTPUT: boolean
  
  RETURN input.request.hasHeader('Accept-Encoding')
         AND (
              hasExplicitZeroQuality(input.request, input.algorithmOffered)
              OR hasWildcardZeroQuality(input.request)
              OR hasWildcardWithExplicitZeroOverride(input.request, input.algorithmOffered)
         )
         AND middlewareStillCompresses(input.request, input.algorithmOffered)
END FUNCTION
```

**Bug 5 - Partial Response Handling:**
```
FUNCTION isBugCondition_5(input)
  INPUT: input of type {response: Response}
  OUTPUT: boolean
  
  RETURN input.response.status == 206
         AND input.response.hasHeader('Content-Range')
         AND middlewareCompresses(input.response)
         AND input.response.getHeader('Content-Range') remainsUnchanged
END FUNCTION
```

**P2: Functional Defects**

**Bug 6 - Fallback Gating:**
```
FUNCTION isBugCondition_6(input)
  INPUT: input of type {request: Request, config: Config}
  OUTPUT: boolean
  
  RETURN input.config.try_multiple_encodings == true
         AND input.config.algorithm NOT IN acceptedEncodings(input.request)
         AND exists(laterCandidateInOrder) WHERE laterCandidateInOrder IN acceptedEncodings(input.request)
         AND middlewareReturnsUncompressed(input.request)
END FUNCTION
```

**Bug 7 - User-Agent Vary:**
```
FUNCTION isBugCondition_7(input)
  INPUT: input of type {request: Request, response: Response, algorithm: string}
  OUTPUT: boolean
  
  RETURN compressionDecisionDependsOn(input.request.getHeader('User-Agent'), input.algorithm)
         AND input.response.hasHeader('Vary')
         AND 'User-Agent' NOT IN input.response.getHeader('Vary')
END FUNCTION
```

**Bug 8 - ETag Handling:**
```
FUNCTION isBugCondition_8(input)
  INPUT: input of type {response: Response}
  OUTPUT: boolean
  
  RETURN input.response.hasHeader('ETag')
         AND input.response.getHeader('ETag').isStrong()
         AND middlewareCompresses(input.response)
         AND input.response.getHeader('ETag') remainsUnchanged
END FUNCTION
```

**Bug 9 - Case Normalization:**
```
FUNCTION isBugCondition_9(input)
  INPUT: input of type {request: Request, config: Config}
  OUTPUT: boolean
  
  RETURN input.request.getHeader('Accept-Encoding').hasUppercaseEncodingName()
         AND input.config.algorithm.isLowercase()
         AND middlewareFails ToMatchCaseSensitively(input.request, input.config.algorithm)
END FUNCTION
```

**Bug 10 - Brotli Detection:**
```
FUNCTION isBugCondition_10(input)
  INPUT: input of type {content: string}
  OUTPUT: boolean
  
  RETURN brotli_decompress(input.content) IN ['', '0']
         AND isBrotliEncoded(input.content) == false
END FUNCTION
```

**Bug 11 - Test Fixtures:**
```
FUNCTION isBugCondition_11(input)
  INPUT: input of type {testName: string, fixture: Content}
  OUTPUT: boolean
  
  RETURN input.testName IN ['pre-compressed', 'first-candidate', 'vary-preservation']
         AND (
              compressedSize(input.fixture) < minLength
              OR input.fixture.encodingRequested == 'identity'
              OR count(input.fixture.acceptedEncodings) == 1
         )
         AND testPasses WITHOUT exercisingPromisedBehavior
END FUNCTION
```

**Bug 12 - Deadlock Risk:**
```
FUNCTION isBugCondition_12(input)
  INPUT: input of type {childProcess: Process}
  OUTPUT: boolean
  
  RETURN input.childProcess.stderrSize > pipeBufferSize
         AND parentDrainsStdoutFirst()
         AND childBlocksOnStderrWrite()
         AND parentWaitsForStdoutEOF()
END FUNCTION
```

**P3: Static Analysis Contract Defect**

**Bug 13 - PHPDoc Accuracy:**
```
FUNCTION isBugCondition_13(input)
  INPUT: input of type {declaration: array, phpDoc: string}
  OUTPUT: boolean
  
  RETURN input.declaration.hasKey('name')
         AND input.phpDoc NOT documentsKey('name')
         AND callersAccessKey('name')
         AND phpStanReportsMissingOffset('name')
END FUNCTION
```

### Examples

**Bug 1 Example:**
- **Input**: Response with `Vary: Origin, Accept-Language`, gzip compression applied
- **Current**: `Vary: Accept-Encoding` (original headers lost)
- **Expected**: `Vary: Origin, Accept-Language, Accept-Encoding`

**Bug 2 Example:**
- **Input**: `try_multiple_encodings=true`, `Accept-Encoding: gzip, br`, 20KB base64 random data
- **Current**: Content double-compressed (gzip then brotli), advertises only `Content-Encoding: br`
- **Expected**: Single gzip compression, advertises `Content-Encoding: gzip`

**Bug 3 Example:**
- **Input**: Response with `Content-Encoding: gzip` and gzip-compressed body
- **Current**: Body compressed again (double gzip), header unchanged
- **Expected**: Compression skipped, body and header unchanged

**Bug 4 Example:**
- **Input**: `Accept-Encoding: gzip;q=0`, `algorithm=gzip`
- **Current**: Response compressed with gzip despite explicit refusal
- **Expected**: Response uncompressed (identity)

**Bug 5 Example:**
- **Input**: HTTP 206 with `Content-Range: bytes 0-10999/20000`, 11KB body
- **Current**: Body compressed to 73 bytes, `Content-Range` unchanged (describes wrong representation)
- **Expected**: Compression skipped, body and range unchanged

**Bug 6 Example:**
- **Input**: `algorithm=br`, `try_multiple_encodings=true`, order `gzip,br`, `Accept-Encoding: gzip`
- **Current**: Uncompressed response (fallback blocked)
- **Expected**: Compressed with gzip (fallback allowed)

**Bug 7 Example:**
- **Input**: `User-Agent: axios/1.7.2` (brotli refused), `User-Agent: Mozilla/5.0` (brotli allowed), both with `Accept-Encoding: br`
- **Current**: Both responses have `Vary: Accept-Encoding` only; cache cannot distinguish
- **Expected**: Both responses have `Vary: Accept-Encoding, User-Agent`

**Bug 8 Example:**
- **Input**: Response with `ETag: "original"`, 11KB body compressed to 73 bytes
- **Current**: Compressed response retains `ETag: "original"` (invalid strong validator)
- **Expected**: ETag removed, weakened to `W/"original"`, or recalculated

**Bug 9 Example:**
- **Input**: `Accept-Encoding: GZIP`, `algorithm=gzip`
- **Current**: Uncompressed response (case-sensitive match fails)
- **Expected**: Compressed with gzip (case-insensitive match succeeds)

**Bug 10 Example:**
- **Input**: `brotli_compress('')` or `brotli_compress('0')`
- **Current**: `isBrotliEncoded()` returns `false`
- **Expected**: `isBrotliEncoded()` returns `true`

**Bug 11 Example:**
- **Input**: Pre-compressed test with 11KB repeated text gzipped to <1KB
- **Current**: Test passes because body falls below `min_length`, guard never exercises
- **Expected**: Test uses deterministic incompressible fixture remaining above threshold

**Bug 12 Example:**
- **Input**: Child script writes 1MB to stderr before stdout
- **Current**: Parent waits for stdout EOF, child blocks on stderr pipe, deadlock
- **Expected**: Parent drains both streams concurrently, execution completes

**Bug 13 Example:**
- **Input**: `GuardIndex::declaration()` returns array with `name` field
- **Current**: PHPDoc documents `array{section: string, line: int}|null`, PHPStan errors on `name` access
- **Expected**: PHPDoc documents `array{section: string, line: int, name: ?string}|null`

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**

The following behaviors must remain identical after all fixes:

1. **Normal compression path**: Responses meeting all criteria (correct type, sufficient length, no existing encoding, accepted by client) continue to compress with negotiated algorithm
2. **Identity requests**: `Accept-Encoding: identity` continues to return uncompressed content
3. **Minimum length threshold**: Bodies below `min_length` continue to skip compression
4. **Content type filtering**: Incompressible types continue to skip compression
5. **Status code filtering**: Unsuccessful status codes continue to follow configured compression behavior
6. **Server preference ordering**: Multiple encodings continue to select based on server preference among accepted clients
7. **Encoder output**: Gzip, brotli, and zstd encoders continue to return compressed content with appropriate headers
8. **Compression efficiency**: Content compressing below original size continues to use compressed version
9. **Test coverage**: All 528 tests continue to pass with 100% line coverage on `src/` files
10. **Configuration loading**: Config values from `config/response-compression.php` continue to parse and apply correctly
11. **Tool compliance**: PHP lint, Rector, and source-only PHPStan analysis continue to pass
12. **Detection helpers**: `isBrotliEncoded()`, `isGzipEncoded()`, `isZstdEncoded()` continue to correctly identify non-empty valid compressed content

**Scope:**

All request/response combinations that do NOT trigger any of the 13 bug conditions should be completely unaffected by these fixes. This includes:
- Normal compression requests with no Vary headers
- Single-encoding compression requests
- Responses without existing Content-Encoding
- Clients accepting encodings with positive quality values
- Full responses (non-206 status)
- Single-algorithm mode with accepted algorithm
- Compression decisions not dependent on User-Agent
- Responses without ETags
- Lowercase encoding names in Accept-Encoding
- Non-empty, non-zero brotli content
- Test cases exercising actual guard conditions
- Child processes with normal output volumes
- Other functions in GuardIndex with correct PHPDoc

## Hypothesized Root Cause

Based on the audit findings and code review:

### P1: Critical Issues

**Bug 1 - Vary Header Overwrite:**
- `Response->headers->add(['Vary' => 'Accept-Encoding'])` replaces existing Vary header instead of appending
- Should use `Response->setVary('Accept-Encoding', false)` which appends without replacing
- Encoders set the header this way; middleware's `vary()` method already uses correct pattern but runs after encoders

**Bug 2 - Multiple Encoding Loop:**
- Loop in `handle()` lines 62-69 has no break after successful compression
- Each iteration calls `shouldCompressForAlgo()` which checks if body still exceeds minimum after previous compression
- Incompressible content remains above threshold after first compression, triggering second compression
- No stop condition prevents multiple applications

**Bug 3 - Pre-Compression Detection:**
- `validateResponse()` checks type, status, length but never examines `Content-Encoding` header
- Missing guard before compression decision allows already-encoded responses through
- Detection helpers exist in `Enc.php` but are never called by middleware

**Bug 4 - Quality Zero Handling:**
- `validateRequest()` uses `Request->getEncodings()` which returns coding names without quality values
- `AcceptEncoding::prefersIdentity()` checks quality but only for `identity` token
- No quality check for compression algorithms before applying them
- `q=0` entries remain in encoding list but are never filtered

**Bug 5 - Partial Response Handling:**
- `validateResponse()` calls `isSuccessful()` which includes status 206
- No separate check for partial responses or `Content-Range` header
- Compression replaces body but `Content-Range` describes original representation bytes

### P2: Functional Defects

**Bug 6 - Fallback Gating:**
- `validateRequest()` checks only single `algorithm` before considering `tryMultipleEncodings()`
- Early return on line 145 blocks multiple-encoding negotiation when single algorithm not accepted
- Multiple-encoding logic should negotiate directly against order list when enabled

**Bug 7 - User-Agent Vary:**
- `nonSupportingUserAgentPrefixes()` consults User-Agent for brotli/zstd decisions
- Variation header set by `vary()` includes only `Accept-Encoding`
- No conditional Vary header logic based on which negotiation inputs were consulted

**Bug 8 - ETag Handling:**
- Encoders call `setContent()` to replace body but never examine or modify `ETag` header
- Strong ETag survives compression with completely different bytes
- No ETag removal, weakening, or recalculation logic in any encoder

**Bug 9 - Case Normalization:**
- `AcceptEncoding::preferences()` lowercases encoding names from header
- `validateRequest()` compares against lowercase config value `$this->algorithm()`
- But `Request->getEncodings()` returns Symfony's parsed values which may retain original case
- Membership check on line 267 is case-sensitive string comparison

**Bug 10 - Brotli Detection:**
- `Enc::isBrotliEncoded()` lines 24-26 uses `if ($decoded !== false && $decoded !== '' && $decoded !== '0')`
- Decompression returning `''` or `'0'` is treated as failure, not as valid result
- Conflates decompression error (`false`) with valid empty/zero content

**Bug 11 - Test Fixtures:**
- Pre-compressed test compresses 11KB repeated text which gzips to <1KB, falling below minimum
- First-candidate test supplies only one accepted encoding, never exercising multi-candidate logic
- Vary-preservation test requests `identity`, bypassing all encoder header writes
- Tests pass without executing the conditions their names promise

**Bug 12 - Deadlock Risk:**
- `ReleaseRepo::script()` lines 416-431 calls `fread($stdout)` to EOF before reading `$stderr`
- Child with large stderr fills pipe buffer and blocks before closing stdout
- Parent waits indefinitely for stdout EOF that will never arrive
- No execution timeout; process hangs

### P3: Static Analysis

**Bug 13 - PHPDoc Accuracy:**
- `GuardIndex::declaration()` returns array with `['section' => ..., 'line' => ..., 'name' => ...]`
- PHPDoc documents only `array{section: string, line: int}|null`
- Omits `name: ?string` field that callers consume
- PHPStan reports undefined offset errors on valid code

## Correctness Properties

Property 1: Bug Condition 1 - Vary Header Preservation

_For any_ response where existing Vary headers are present and compression is applied, the fixed encoders SHALL append `Accept-Encoding` to existing variation markers using `setVary()` with `false` parameter, preserving all original Vary values including wildcard semantics.

**Validates: Requirements 2.1**

Property 2: Bug Condition 2 - Single Compression Application

_For any_ request accepting multiple encodings with try_multiple_encodings enabled, the fixed middleware SHALL stop after the first successful compression and advertise only that single encoding in Content-Encoding header.

**Validates: Requirements 2.2**

Property 3: Bug Condition 3 - Pre-Compression Skip

_For any_ response already carrying Content-Encoding header with gzip, br, or zstd value, the fixed middleware SHALL skip compression entirely to avoid double-encoding.

**Validates: Requirements 2.3**

Property 4: Bug Condition 4 - Quality Zero Refusal

_For any_ request explicitly refusing an encoding with q=0 (direct, wildcard, or overridden), the fixed middleware SHALL check effective quality value and require it to be positive before compressing with that encoding.

**Validates: Requirements 2.4**

Property 5: Bug Condition 5 - Partial Response Skip

_For any_ response with status 206 or Content-Range header, the fixed middleware SHALL skip compression unless coding and range selection are explicitly coordinated.

**Validates: Requirements 2.5**

Property 6: Bug Condition 6 - Fallback Negotiation

_For any_ request in multiple-encoding mode where single algorithm is not accepted but later candidate is accepted, the fixed middleware SHALL negotiate directly against candidate list and compress with accepted encoding.

**Validates: Requirements 2.6**

Property 7: Bug Condition 7 - User-Agent Variation

_For any_ compression decision depending on User-Agent header (via prefix lists), the fixed middleware SHALL include User-Agent in Vary header along with Accept-Encoding.

**Validates: Requirements 2.7**

Property 8: Bug Condition 8 - ETag Adjustment

_For any_ response with strong ETag that undergoes compression, the fixed encoders SHALL remove, weaken (prefix with W/), or recalculate the ETag to reflect the encoded representation.

**Validates: Requirements 2.8**

Property 9: Bug Condition 9 - Case Insensitive Matching

_For any_ request with encoding names in any case (GZIP, Gzip, gzip), the fixed middleware SHALL normalize to lowercase before comparison with configuration values.

**Validates: Requirements 2.9**

Property 10: Bug Condition 10 - Valid Empty Detection

_For any_ brotli-compressed content that decompresses to empty string or '0', the fixed isBrotliEncoded() SHALL return true, distinguishing decompression failure (false) from valid empty results (true).

**Validates: Requirements 2.10**

Property 11: Bug Condition 11 - Effective Test Fixtures

_For any_ regression test claiming to exercise a guard condition, the fixed test SHALL use deterministic incompressible fixtures that remain above compression threshold and actually trigger the guard being tested.

**Validates: Requirements 2.11**

Property 12: Bug Condition 12 - Deadlock Prevention

_For any_ child process executed by ReleaseRepo::script(), the fixed implementation SHALL drain stdout and stderr concurrently or redirect to separate files, and SHALL bound execution time with process cleanup.

**Validates: Requirements 2.12**

Property 13: Bug Condition 13 - Complete PHPDoc

_For any_ function returning an array shape consumed by callers, the fixed PHPDoc SHALL document all returned fields including name: ?string that GuardIndex::declaration() actually returns.

**Validates: Requirements 2.13**

Property 14: Preservation - Normal Compression Path

_For any_ request/response meeting all compression criteria without triggering any bug condition, the fixed middleware SHALL produce exactly the same compressed output with same headers as the original implementation.

**Validates: Requirements 3.1, 3.6, 3.7, 3.8**

Property 15: Preservation - Configuration Behavior

_For any_ configuration value (identity requests, minimum length, content types, status codes), the fixed middleware SHALL produce exactly the same compression decision as the original implementation.

**Validates: Requirements 3.2, 3.3, 3.4, 3.5, 3.10**

Property 16: Preservation - Test Coverage

_For any_ test in the existing 528-test suite not directly addressing the 13 bugs, the fixed implementation SHALL pass that test with same coverage metrics (100% on src/ files).

**Validates: Requirements 3.9, 3.11, 3.12**

## Fix Implementation

### Changes Required

All changes follow the package's architectural patterns and preserve the existing pipeline. Each fix is minimal and surgical.

#### Bug 1: Vary Header Preservation

**Files**: 
- `src/Encoders/GzipEncoder.php` (lines 20-24)
- `src/Encoders/BrotliEncoder.php` (lines 22-26)
- `src/Encoders/ZstdEncoder.php` (lines 22-26)

**Specific Changes**:

1. **Replace `headers->add()` with `setVary()` in all three encoders**:
   ```php
   // Current (line 20-24 in GzipEncoder):
   $response->headers->add([
       'Content-Encoding' => 'gzip',
       'Vary' => 'Accept-Encoding',
       'Content-Length' => strlen($compressed),
   ]);
   
   // Fixed:
   $response->headers->set('Content-Encoding', 'gzip');
   $response->headers->set('Content-Length', strlen($compressed));
   $response->setVary('Accept-Encoding', false); // false = append, don't replace
   ```

2. **Apply identical change to BrotliEncoder (lines 22-26) and ZstdEncoder (lines 22-26)**

**Rationale**: `setVary()` with `false` parameter appends to existing Vary header rather than replacing it, and handles wildcard semantics correctly. This is the same pattern the middleware's `vary()` method already uses successfully.

#### Bug 2: Multiple Encoding Loop

**File**: `src/Middleware/CompressResponse.php` (lines 62-69)

**Specific Changes**:

```php
// Current:
if ($this->tryMultipleEncodings()) {
    foreach ($this->encodingOrder() as $encoding) {
        if ($this->shouldCompressForAlgo($encoding, $request, $response, $requestUri)) {
            $response = $this->encode($encoding, $response, $requestUri);
        }
    }
    return $this->vary($response);
}

// Fixed:
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

**Rationale**: The first encoding that passes `shouldCompressForAlgo()` and successfully compresses is the winner. No subsequent iterations should occur.

#### Bug 3: Pre-Compression Detection

**File**: `src/Middleware/CompressResponse.php` (lines 148-193 in `validateResponse()`)

**Specific Changes**:

```php
// Add new check in validateResponse() after line 162 (after status check):
private function validateResponse(Response $response, string $requestUri): bool
{
    if (! $this->validateResponseType($response)) {
        // ... existing type check ...
    }

    if ($response->isSuccessful() && $response->getStatusCode() === 304) {
        // ... existing 304 check ...
    }

    // NEW: Check for existing Content-Encoding
    if ($response->headers->has('Content-Encoding')) {
        $this->logDebugStatus(
            Diagnostic::ResponseSkipped,
            ['reason' => 'already_encoded', 'uri' => $requestUri]
        );
        return false;
    }

    // ... rest of existing validation ...
}
```

**Rationale**: Any response with a Content-Encoding header has already been transformed and should not be compressed again. This is the simplest check that prevents all double-encoding scenarios. The detection helpers in `Enc.php` are not needed here; presence of the header is sufficient.

#### Bug 4: Quality Zero Handling

**File**: `src/Middleware/CompressResponse.php` (lines 246-272 in `validateRequestPerAlgoAndUserAgent()`)

**Specific Changes**:

```php
// Add quality check after line 267 (after Accept-Encoding check):
private function validateRequestPerAlgoAndUserAgent(
    string $compressionAlgorithm,
    SymfonyRequest $request,
    Response $response,
    string $requestUri
): bool {
    if (! in_array($compressionAlgorithm, $request->getEncodings(), true)) {
        // ... existing log ...
        return false;
    }

    // NEW: Check quality value
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

    // ... rest of existing user-agent validation ...
}
```

**Rationale**: Uses the existing `AcceptEncoding::preferences()` method that already parses quality values correctly. Checks the specific encoding's quality, falls back to wildcard, and refuses if zero or negative.

#### Bug 5: Partial Response Handling

**File**: `src/Middleware/CompressResponse.php` (lines 163-193 in `validateResponse()`)

**Specific Changes**:

```php
// Add check after existing 304 check (after line 170):
private function validateResponse(Response $response, string $requestUri): bool
{
    // ... existing type and 304 checks ...

    // NEW: Check for partial responses
    if ($response->getStatusCode() === 206 || $response->headers->has('Content-Range')) {
        $this->logDebugStatus(
            Diagnostic::ResponseSkipped,
            ['reason' => 'partial_content', 'uri' => $requestUri]
        );
        return false;
    }

    // ... rest of existing validation ...
}
```

**Rationale**: Partial responses describe specific byte ranges of the original representation. Compressing changes the bytes, invalidating the range. Skip any 206 status or any response with Content-Range header (covers edge cases of 200 with range).

#### Bug 6: Fallback Gating

**File**: `src/Middleware/CompressResponse.php` (lines 138-147 in `validateRequest()`)

**Specific Changes**:

```php
// Current:
private function validateRequest(SymfonyRequest $request, string $requestUri): bool
{
    return $this->validateRequestPerAlgoAndUserAgent(
        $this->algorithm(),
        $request,
        $response,  // Note: $response not available here in current code
        $requestUri
    );
}

// Fixed approach - reorder logic in handle() method (lines 40-78):
public function handle(SymfonyRequest $request, Closure $next): Response
{
    // ... existing early exits ...

    $requestUri = $request->getRequestUri();

    // NEW: In multiple-encoding mode, validate against accepted encodings directly
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
        // Single-algorithm mode uses existing validation
        if (! $this->validateRequest($request, $requestUri)) {
            $this->logDebugStatus(Diagnostic::CompressionNotAllowed);
            return $this->vary($next($request));
        }
    }

    $response = $next($request);

    if ($this->tryMultipleEncodings()) {
        // ... existing loop with break from Bug 2 fix ...
    }

    // ... rest of existing logic ...
}
```

**Rationale**: Multiple-encoding mode should negotiate against the full candidate list, not gate on the single algorithm. This requires checking acceptance before getting the response, so validation moves into `handle()` itself for the multiple-encoding path.

#### Bug 7: User-Agent Variation

**File**: `src/Middleware/CompressResponse.php` (modify `vary()` method lines 96-108 and track User-Agent consultation)

**Specific Changes**:

1. **Add property to track User-Agent consultation**:
   ```php
   // Add after line 38:
   private bool $userAgentConsulted = false;
   ```

2. **Set flag in `validateRequestPerAlgoAndUserAgent()` when User-Agent is checked**:
   ```php
   // In validateRequestPerAlgoAndUserAgent() after line 274:
   if ($this->nonSupportingUserAgentPrefixes($compressionAlgorithm)) {
       $this->userAgentConsulted = true; // NEW
       // ... existing user-agent check ...
   }
   ```

3. **Conditionally add User-Agent to Vary header**:
   ```php
   // In vary() method after line 116:
   private function vary(Response $response): Response
   {
       // ... existing Accept-Encoding vary logic ...
       
       // NEW: Add User-Agent if consulted
       if ($this->userAgentConsulted) {
           $response->setVary('User-Agent', false);
       }
       
       return $response;
   }
   ```

**Rationale**: Tracks whether User-Agent influenced the compression decision. Only adds User-Agent to Vary when it was actually consulted, avoiding unnecessary cache fragmentation.

#### Bug 8: ETag Handling

**Files**: All three encoders (`GzipEncoder.php`, `BrotliEncoder.php`, `ZstdEncoder.php`)

**Specific Changes**:

```php
// Add after setContent() in each encoder's handle() method:
public function handle(Response $response): Response
{
    $compressed = gzencode((string) $response->getContent(), $this->level());

    if ($compressed) {
        $response->setContent($compressed);
        
        // NEW: Handle ETag
        if ($response->headers->has('ETag')) {
            $etag = $response->headers->get('ETag');
            // If strong ETag (quoted, not W/ prefix), weaken it
            if ($etag && !str_starts_with($etag, 'W/') && str_starts_with($etag, '"')) {
                $response->headers->set('ETag', 'W/' . $etag);
            }
        }

        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', strlen($compressed));
        $response->setVary('Accept-Encoding', false);
    }

    return $response;
}
```

**Rationale**: Weakening the ETag (adding W/ prefix) is the safest approach that preserves validator functionality while acknowledging the representation changed. This is standard practice per RFC 7232. Alternative (removal) would work but loses validation benefit. Recalculation is complex and unnecessary.

#### Bug 9: Case Normalization

**File**: `src/Middleware/CompressResponse.php` (line 267 in `validateRequestPerAlgoAndUserAgent()`)

**Specific Changes**:

```php
// Current (line 267):
if (! in_array($compressionAlgorithm, $request->getEncodings(), true)) {

// Fixed:
$requestEncodings = array_map('strtolower', $request->getEncodings());
if (! in_array(strtolower($compressionAlgorithm), $requestEncodings, true)) {
```

**Rationale**: Normalizes both sides of comparison to lowercase. The config value `$compressionAlgorithm` is already lowercase, but normalizing it explicitly makes the intent clear and future-proof.

#### Bug 10: Brotli Detection

**File**: `src/Support/Enc.php` (lines 24-26 in `isBrotliEncoded()`)

**Specific Changes**:

```php
// Current:
public static function isBrotliEncoded(string $content): bool
{
    $decoded = @brotli_uncompress($content);
    return $decoded !== false && $decoded !== '' && $decoded !== '0';
}

// Fixed:
public static function isBrotliEncoded(string $content): bool
{
    $decoded = @brotli_uncompress($content);
    // Distinguish decompression failure (false) from valid empty/zero results
    return $decoded !== false;
}
```

**Rationale**: Empty string and '0' are valid decompression results. Only `false` indicates failure. The original logic incorrectly conflated valid results with errors.

#### Bug 11: Test Fixtures

**Files**: 
- `tests/Unit/Middleware/CompressResponseTest.php` (multiple test methods)
- `tests/Pest.php` (helper factory)

**Specific Changes**:

1. **Add deterministic incompressible fixture generator in Pest.php**:
   ```php
   // Add after line 71:
   function incompressibleContent(int $bytes = 2048): string
   {
       // Base64-encoded random-like data is deterministic and incompressible
       // Use fixed seed for reproducibility
       $seed = 'ResponseCompressionTestFixtureSeed';
       $hash = hash('sha256', $seed);
       $pattern = base64_encode(hex2bin($hash));
       
       return str_repeat($pattern, (int) ceil($bytes / strlen($pattern)))
           . substr($pattern, 0, $bytes % strlen($pattern));
   }
   ```

2. **Fix pre-compressed test (lines 191-203)**:
   ```php
   // Replace fixture to ensure compressed size > min_length:
   $content = incompressibleContent(3000); // Large enough that gzip > 1024 bytes
   $gzipped = gzencode($content, 9);
   
   // Assert fixture meets requirements
   expect(strlen($gzipped))->toBeGreaterThan(1024, 
       'Fixture compressed size must exceed min_length for test to be effective');
   
   $response = response($gzipped)
       ->header('Content-Encoding', 'gzip')
       ->header('Content-Length', strlen($gzipped));
   
   // ... rest of test ...
   ```

3. **Fix first-candidate test (lines 278-305)** to accept two encodings but configure only first
4. **Fix Vary-preservation test (lines 582-590)** to request gzip, not identity, so encoder runs

**Rationale**: Tests must exercise the actual guard conditions they claim to test. Deterministic incompressible fixtures ensure consistent behavior across runs and platforms.

#### Bug 12: Deadlock Prevention

**File**: `tests/Support/ReleaseRepo.php` (lines 416-431 in `script()` method)

**Specific Changes**:

```php
// Current (lines 416-431):
$stdout = fread($pipes[1], 8192);
fclose($pipes[1]);
$stderr = fread($pipes[2], 8192);
fclose($pipes[2]);

// Fixed - use stream_select() for concurrent reading:
private function drainPipes(array $pipes): array
{
    $stdout = '';
    $stderr = '';
    $read = [$pipes[1], $pipes[2]];
    $write = null;
    $except = null;
    $timeout = 30; // 30 second timeout
    $endTime = time() + $timeout;
    
    while (!feof($pipes[1]) || !feof($pipes[2])) {
        if (time() > $endTime) {
            throw new RuntimeException('Process execution timeout');
        }
        
        $read = array_filter([$pipes[1], $pipes[2]], fn($pipe) => is_resource($pipe) && !feof($pipe));
        if (empty($read)) {
            break;
        }
        
        if (stream_select($read, $write, $except, 1) > 0) {
            foreach ($read as $pipe) {
                $chunk = fread($pipe, 8192);
                if ($chunk !== false) {
                    if ($pipe === $pipes[1]) {
                        $stdout .= $chunk;
                    } else {
                        $stderr .= $chunk;
                    }
                }
            }
        }
    }
    
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    return [$stdout, $stderr];
}

// In script() method:
[$stdout, $stderr] = $this->drainPipes($pipes);
```

**Rationale**: `stream_select()` allows reading from whichever pipe has data available, preventing the deadlock. Adding a timeout bounds execution time. This is the standard PHP approach for concurrent pipe reading.

#### Bug 13: PHPDoc Accuracy

**File**: `tests/Support/GuardIndex.php` (line 1388 PHPDoc)

**Specific Changes**:

```php
// Current (line 1388):
/**
 * @return array{section: string, line: int}|null
 */
private function declaration(string $row): ?array

// Fixed:
/**
 * @return array{section: string, line: int, name: ?string}|null
 */
private function declaration(string $row): ?array
```

**Rationale**: Documents the actual return value that callers consume. The `name` field is already returned by the implementation; PHPDoc just needs to acknowledge it.

### Implementation Order

The fixes should be implemented in this sequence to maintain test suite green status throughout:

1. **Bug 13** (PHPDoc) - Trivial documentation fix, no behavior change
2. **Bug 10** (Brotli detection) - Simple logic fix in helper, unit tested
3. **Bug 9** (Case normalization) - Single-line fix, immediately testable
4. **Bug 1** (Vary header) - Encoder changes, preserves existing behavior
5. **Bug 8** (ETag) - Encoder changes, builds on Bug 1 encoder modifications
6. **Bug 3** (Pre-compression) - Validation guard, straightforward addition
7. **Bug 5** (Partial response) - Validation guard, straightforward addition
8. **Bug 4** (Quality zero) - Validation logic, uses existing parser
9. **Bug 2** (Multiple encoding) - Control flow fix, requires Bug 1 encoder changes
10. **Bug 7** (User-Agent Vary) - Tracking and variation, requires Bug 1 changes
11. **Bug 6** (Fallback gating) - Larger refactor of handle() method
12. **Bug 11** (Test fixtures) - Update tests to use fixes from Bugs 1-10
13. **Bug 12** (Deadlock) - Test infrastructure, independent of middleware changes

This order allows incremental verification and minimizes the chance of introducing new issues.

### Code Organization

No new files are required. All changes modify existing files within the current architecture:
- Middleware remains in `src/Middleware/CompressResponse.php`
- Encoders remain in `src/Encoders/`
- Support classes remain in `src/Support/`
- Test changes remain in `tests/Unit/` and `tests/Support/`

## Testing Strategy

### Validation Approach

The testing strategy follows a three-phase approach:
1. **Exploratory Bug Condition Checking**: Surface counterexamples demonstrating each bug on unfixed code
2. **Fix Checking**: Verify fixed code correctly handles all bug conditions
3. **Preservation Checking**: Verify fixed code produces identical results for non-buggy inputs

Each bug requires specific test cases. Property-based testing is recommended for preservation checking across the large input domain.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples demonstrating all 13 bugs BEFORE implementing fixes. Confirm root cause analysis.

**Test Plan**: Write focused integration tests for each bug using the reproduction cases from the audit report. Run against UNFIXED code to observe failures.

**Test Cases**:

1. **Bug 1 - Vary Overwrite**: Response with `Vary: Origin` → after gzip → assert `Vary` contains both `Origin` and `Accept-Encoding` (will FAIL showing only `Accept-Encoding`)

2. **Bug 2 - Multiple Encoding**: `try_multiple_encodings=true`, accept `gzip,br`, incompressible 20KB → assert single gzip, not double-compressed (will FAIL showing brotli-wrapped gzip)

3. **Bug 3 - Pre-Compression**: Response with `Content-Encoding: gzip` and gzipped body → assert no re-compression (will FAIL showing double gzip)

4. **Bug 4 - Quality Zero**: `Accept-Encoding: gzip;q=0` → assert identity response (will FAIL showing gzip)

5. **Bug 5 - Partial Response**: Status 206 with `Content-Range`, 11KB body → assert no compression (will FAIL showing compressed with invalid range)

6. **Bug 6 - Fallback Gating**: `algorithm=br`, accept only gzip, multiple mode → assert gzip compression (will FAIL showing identity)

7. **Bug 7 - User-Agent Vary**: Different User-Agents with brotli → assert `Vary` includes `User-Agent` (will FAIL showing only `Accept-Encoding`)

8. **Bug 8 - ETag Handling**: Response with `ETag: "abc"` → after compression → assert ETag changed (will FAIL showing unchanged strong ETag)

9. **Bug 9 - Case Normalization**: `Accept-Encoding: GZIP` → assert gzip compression (will FAIL showing identity)

10. **Bug 10 - Brotli Detection**: `brotli_compress('')` → `isBrotliEncoded()` → assert true (will FAIL showing false)

11. **Bug 11 - Test Fixture**: Pre-compressed test fixture → assert compressed size > min_length (will FAIL showing < min_length)

12. **Bug 12 - Deadlock**: Child script writing 1MB stderr → `ReleaseRepo::script()` with 5s timeout → assert completion (will FAIL with timeout)

13. **Bug 13 - PHPDoc**: `GuardIndex::declaration()` → access `name` field → PHPStan → assert no error (will FAIL with undefined offset)

**Expected Counterexamples**: Each test should fail precisely as described in the audit report, confirming the root cause hypothesis.

### Fix Checking

**Goal**: Verify that for all inputs where each bug condition holds, the fixed implementation produces expected behavior.

**Pseudocode per Bug**:

```
// Bug 1:
FOR ALL response WHERE response.hasVaryHeader() AND isCompressed(response) DO
  ASSERT response.headers.vary CONTAINS originalVaryValues
  ASSERT response.headers.vary CONTAINS 'Accept-Encoding'
END FOR

// Bug 2:
FOR ALL request WHERE tryMultiple AND acceptsMultiple(request) DO
  response := fixed_handle(request)
  ASSERT countEncodings(response.content) == 1
  ASSERT response.headers.contentEncoding HAS_SINGLE_VALUE
END FOR

// Bug 3:
FOR ALL response WHERE response.hasHeader('Content-Encoding') DO
  result := fixed_validateResponse(response)
  ASSERT result == false
END FOR

// Bug 4:
FOR ALL request WHERE hasZeroQuality(request, encoding) DO
  result := fixed_validateRequest(request, encoding)
  ASSERT result == false
END FOR

// Bug 5:
FOR ALL response WHERE response.status == 206 OR response.hasHeader('Content-Range') DO
  result := fixed_validateResponse(response)
  ASSERT result == false
END FOR

// Bug 6:
FOR ALL request WHERE tryMultiple AND !accepts(primaryAlgorithm) AND accepts(laterCandidate) DO
  response := fixed_handle(request)
  ASSERT response.isCompressed()
  ASSERT response.headers.contentEncoding == laterCandidate
END FOR

// Bug 7:
FOR ALL request WHERE userAgentInfluencesDecision(request) DO
  response := fixed_handle(request)
  ASSERT response.headers.vary CONTAINS 'User-Agent'
END FOR

// Bug 8:
FOR ALL response WHERE response.hasStrongETag() AND isCompressed(response) DO
  ASSERT response.etag.isWeak() OR !response.hasHeader('ETag')
END FOR

// Bug 9:
FOR ALL request WHERE hasUppercaseEncoding(request) DO
  result := fixed_validateRequest(request)
  ASSERT result == matchesExpectedEncoding(request)
END FOR

// Bug 10:
FOR ALL content WHERE brotli_decompress(content) IN ['', '0'] DO
  ASSERT fixed_isBrotliEncoded(content) == true
END FOR

// Bug 11:
FOR ALL test IN regressionTests DO
  ASSERT test.fixture.compressedSize > minLength
  ASSERT test.exercisesClaimedBehavior()
END FOR

// Bug 12:
FOR ALL childProcess WHERE childProcess.stderrSize > 100KB DO
  result := fixed_script(childProcess)
  ASSERT result.completedWithinTimeout(30seconds)
END FOR

// Bug 13:
PHPStan analysis of fixed_declaration() callers
ASSERT no undefined offset errors
```

### Preservation Checking

**Goal**: Verify that for all inputs where bug conditions do NOT hold, the fixed implementation produces identical results to the original.

**Pseudocode**:

```
FOR ALL input WHERE NOT anyBugCondition(input) DO
  ASSERT original_handle(input) == fixed_handle(input)
END FOR

// Specific preservation tests:
FOR ALL request WHERE acceptsEncodingNormally(request) DO
  ASSERT original_handle(request).body == fixed_handle(request).body
  ASSERT original_handle(request).headers == fixed_handle(request).headers
END FOR

FOR ALL response WHERE meetsCompressionCriteria(response) AND noExistingEncoding(response) DO
  ASSERT original_encode(response) == fixed_encode(response)
END FOR

FOR ALL config IN [identity, minLength, contentTypes, statusCodes] DO
  ASSERT original_behavior(config) == fixed_behavior(config)
END FOR
```

**Testing Approach**: Property-based testing is strongly recommended because:
- Generates hundreds of test cases automatically across input domain
- Catches edge cases manual tests miss
- Provides strong guarantees that non-buggy paths are unchanged
- Validates preservation across all 3.1-3.12 requirements simultaneously

**Test Plan**:

1. **Property Test: Normal Compression Preserved**
   - Generate: Random responses (types, sizes, statuses) without bug conditions
   - Assert: Same compression output and headers before/after fixes

2. **Property Test: Configuration Behavior Preserved**
   - Generate: Random config values, various request patterns
   - Assert: Same compression decisions before/after fixes

3. **Property Test: Encoder Output Preserved**
   - Generate: Random content, various encoding parameters
   - Assert: Same compressed bytes before/after fixes (when no bug conditions)

### Unit Tests

- Test each validation guard (pre-compression, partial response, quality zero) in isolation
- Test each encoder's Vary and ETag handling separately
- Test AcceptEncoding quality parsing with all q=0 patterns
- Test Enc.isBrotliEncoded() with empty/zero content
- Test case-insensitive encoding matching
- Test ReleaseRepo pipe draining with various output sizes

### Property-Based Tests

- Generate random Accept-Encoding headers → verify correct quality evaluation
- Generate random response Vary headers → verify preservation after compression
- Generate random content sizes → verify min_length threshold respected
- Generate random encoding orders → verify server preference maintained
- Generate random User-Agent strings → verify Vary header correctness
- Generate random ETag formats → verify appropriate handling (strong/weak/absent)

### Integration Tests

- Full request/response cycle with all 13 bug conditions in various combinations
- Multi-encoding negotiation with quality values, user-agent filtering, and Vary headers
- Cache key scenarios with varying Vary headers and user-agent dependencies
- End-to-end validation that 528 existing tests still pass with same coverage
- Verify composer checks -- --require-all passes (all 11 gate checks green)
- Verify PHPStan max level on src/ passes with zero diagnostics

### Regression Test Suite

After fixes, the complete test validation must show:
- All 13 exploratory tests now PASS (were failing on unfixed code)
- All existing 528 tests continue to PASS
- Line coverage remains 100% on all src/ files
- All bin/ coverage floors continue to pass
- No new PHPStan diagnostics introduced
- python .agents/verify.py passes with zero findings

