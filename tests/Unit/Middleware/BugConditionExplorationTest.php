<?php

declare(strict_types=1);

/* @guards-index test */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bug Condition Exploration Tests
 *
 * These tests demonstrate that bugs exist on UNFIXED code.
 * Each test MUST FAIL initially, confirming the bug.
 * After implementation, they will PASS, confirming the fix works.
 *
 * DO NOT FIX THESE TESTS OR THE CODE WHEN THEY FAIL.
 * The failure is the proof that the bug exists.
 */
describe('Bug 1 - Vary Header Overwrite', function (): void {

    it('should preserve existing Vary headers when adding Accept-Encoding', function (): void {
        // Create a response with existing Vary headers
        $response = new Response(
            getLongContent(),
            200,
            [
                'Content-Type' => 'text/plain',
                'Vary' => 'Origin, Accept-Language',
            ]
        );

        // Apply compression via middleware
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']);
        $result = runCompressResponseMiddleware($request, $response);

        // Get the Vary header value
        $varyHeader = $result->headers->get('Vary');

        // Assert: Vary header should contain ALL three values
        // - Original: Origin, Accept-Language
        // - Added by compression: Accept-Encoding
        //
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug causes Vary to be replaced with only "Accept-Encoding"
        // This loses the original cache variation markers
        expect($varyHeader)->toContain('Origin')
            ->and($varyHeader)->toContain('Accept-Language')
            ->and($varyHeader)->toContain('Accept-Encoding');
    })->group('bug-exploration', 'bug-1');

    it('should preserve wildcard Vary header when adding Accept-Encoding', function (): void {
        // Test with wildcard Vary
        $response = new Response(
            getLongContent(),
            200,
            [
                'Content-Type' => 'text/plain',
                'Vary' => '*',
            ]
        );

        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']);
        $result = runCompressResponseMiddleware($request, $response);

        $varyHeader = $result->headers->get('Vary');

        // Wildcard should be preserved (it means "vary on everything")
        // EXPECTED ON UNFIXED CODE: FAILS
        // Bug replaces "*" with "Accept-Encoding"
        expect($varyHeader)->toBe('*');
    })->group('bug-exploration', 'bug-1');

    it('should handle single existing Vary header when adding Accept-Encoding', function (): void {
        // Test with single Vary value
        $response = new Response(
            getLongContent(),
            200,
            [
                'Content-Type' => 'text/plain',
                'Vary' => 'Origin',
            ]
        );

        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']);
        $result = runCompressResponseMiddleware($request, $response);

        $varyHeader = $result->headers->get('Vary');

        // Should contain both Origin and Accept-Encoding
        // EXPECTED ON UNFIXED CODE: FAILS
        // Bug replaces "Origin" with only "Accept-Encoding"
        expect($varyHeader)->toContain('Origin')
            ->and($varyHeader)->toContain('Accept-Encoding');
    })->group('bug-exploration', 'bug-1');
});

describe('Bug 2 - Multiple Encoding Loop', function (): void {

    it('should compress content only once when trying multiple encodings', function (): void {
        // Configure multiple encodings mode
        config()->set('response-compression.try_multiple_encodings', true);
        config()->set('response-compression.multiple_encodings_order', 'gzip,br');
        config()->set('response-compression.min_length', 1024);

        // Create 20KB of incompressible content (random bytes)
        // Random data compresses poorly, making it more likely both encoders will attempt compression
        $originalContent = random_bytes(20 * 1024);

        // Create a response with the incompressible content
        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'application/octet-stream',
            ]
        );

        // Request with both gzip and br accepted
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip, br']);

        // Apply compression via middleware
        $result = runCompressResponseMiddleware($request, $response);

        // Get the Content-Encoding header
        $contentEncoding = $result->headers->get('Content-Encoding');

        // Assert: Content-Encoding should have exactly ONE value (either 'gzip' or 'br')
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug causes multiple compressions, but only the last encoding is advertised
        // This makes the header look correct even though the body is double-compressed
        expect($contentEncoding)->toBeString()
            ->and($contentEncoding)->not->toBeEmpty()
            ->and($contentEncoding)->toMatch('/^(gzip|br)$/');

        // Get the compressed body
        $compressedBody = $result->getContent();

        // Assert: Single decompression should recover the original content
        // EXPECTED ON UNFIXED CODE: FAILS
        // If the body is double-compressed but only one encoding is advertised,
        // decompressing once will not recover the original content

        // Try to decompress based on the advertised encoding
        $decompressed = match ($contentEncoding) {
            'gzip' => gzdecode($compressedBody),
            'br' => brotli_uncompress($compressedBody),
            default => throw new RuntimeException("Unexpected encoding: {$contentEncoding}"),
        };

        // This should match the original content exactly
        // If it's double-compressed, $decompressed will be compressed data (from first pass)
        // and won't match the original
        expect($decompressed)->toBe($originalContent)
            ->and(strlen($decompressed))->toBe(strlen($originalContent));
    })->group('bug-exploration', 'bug-2');

    it('should produce decodable content when multiple encodings are configured', function (): void {
        // Another variant: test that the body is actually decodable
        config()->set('response-compression.try_multiple_encodings', true);
        config()->set('response-compression.multiple_encodings_order', 'gzip,br');
        config()->set('response-compression.min_length', 1024);

        // Use text content that's easier to verify
        $originalContent = str_repeat('Test content for compression. ', 1000); // ~30KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip, br']);
        $result = runCompressResponseMiddleware($request, $response);

        $contentEncoding = $result->headers->get('Content-Encoding');
        $compressedBody = $result->getContent();

        // Single decompression with the advertised algorithm
        $decompressed = match ($contentEncoding) {
            'gzip' => gzdecode($compressedBody),
            'br' => brotli_uncompress($compressedBody),
            default => throw new RuntimeException("Unexpected encoding: {$contentEncoding}"),
        };

        // EXPECTED ON UNFIXED CODE: FAILS
        // Double compression means the first decompression yields compressed data,
        // not the original text
        expect($decompressed)->toBe($originalContent)
            ->and($decompressed)->toContain('Test content for compression.');
    })->group('bug-exploration', 'bug-2');

    it('should not apply multiple encodings sequentially to the same response', function (): void {
        // Test that specifically checks for sequential compression
        config()->set('response-compression.try_multiple_encodings', true);
        config()->set('response-compression.multiple_encodings_order', 'gzip,br');
        config()->set('response-compression.min_length', 1024);

        // Create content that will definitely trigger compression
        $originalContent = str_repeat('AAAA', 1000); // Very compressible, ~4KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip, br']);
        $result = runCompressResponseMiddleware($request, $response);

        $contentEncoding = $result->headers->get('Content-Encoding');
        $compressedBody = $result->getContent();

        // Decompress once with the advertised encoding
        $decompressed = match ($contentEncoding) {
            'gzip' => gzdecode($compressedBody),
            'br' => brotli_uncompress($compressedBody),
            default => $compressedBody,
        };

        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug applies both encodings, so decompressing once leaves us with
        // content that's still compressed with the first encoding
        expect($decompressed)->toBe($originalContent);
    })->group('bug-exploration', 'bug-2');
});

describe('Bug 3 - Pre-Compression Detection', function (): void {

    it('should not double-encode already gzip-compressed responses', function (): void {
        // Create 20KB of incompressible content (random bytes)
        // This ensures compression would normally be attempted
        $originalContent = random_bytes(20 * 1024);

        // Manually gzip compress the content
        $gzipped = gzencode($originalContent, 9);

        // Create a response with the already-compressed body
        // and the Content-Encoding header indicating it's already gzipped
        $response = new Response(
            $gzipped,
            200,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Encoding' => 'gzip',
            ]
        );

        // Request with gzip accepted
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']);

        // Apply compression via middleware
        $result = runCompressResponseMiddleware($request, $response);

        // Get the response body
        $responseBody = $result->getContent();

        // Assert: Single decompression should recover the original content
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug causes the middleware to compress the already-gzipped body again,
        // resulting in double compression. A single gzdecode() will return
        // compressed data (from the first compression), not the original content.
        $decompressed = gzdecode($responseBody);

        expect($decompressed)->toBe($originalContent)
            ->and(strlen($decompressed))->toBe(strlen($originalContent));
    })->group('bug-exploration', 'bug-3');

    it('should not double-encode already brotli-compressed responses', function (): void {
        // Similar test but with brotli encoding
        $originalContent = random_bytes(20 * 1024);

        // Manually brotli compress the content
        $compressed = brotli_compress($originalContent, 11);

        // Create response with already-compressed body
        $response = new Response(
            $compressed,
            200,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Encoding' => 'br',
            ]
        );

        // Request with brotli accepted
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'br']);

        // Apply compression via middleware
        $result = runCompressResponseMiddleware($request, $response);

        $responseBody = $result->getContent();

        // Assert: Single decompression should recover original content
        // EXPECTED ON UNFIXED CODE: FAILS
        // Bug causes double compression
        $decompressed = brotli_uncompress($responseBody);

        expect($decompressed)->toBe($originalContent)
            ->and(strlen($decompressed))->toBe(strlen($originalContent));
    })->group('bug-exploration', 'bug-3');

    it('should not double-encode already zstd-compressed responses', function (): void {
        // Similar test but with zstd encoding
        $originalContent = random_bytes(20 * 1024);

        // Manually zstd compress the content
        $compressed = zstd_compress($originalContent, 22);

        // Create response with already-compressed body
        $response = new Response(
            $compressed,
            200,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Encoding' => 'zstd',
            ]
        );

        // Request with zstd accepted
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'zstd']);

        // Apply compression via middleware
        $result = runCompressResponseMiddleware($request, $response);

        $responseBody = $result->getContent();

        // Assert: Single decompression should recover original content
        // EXPECTED ON UNFIXED CODE: FAILS
        // Bug causes double compression
        $decompressed = zstd_uncompress($responseBody);

        expect($decompressed)->toBe($originalContent)
            ->and(strlen($decompressed))->toBe(strlen($originalContent));
    })->group('bug-exploration', 'bug-3');

    it('should preserve Content-Encoding header when response is already compressed', function (): void {
        // Test that the Content-Encoding header is preserved
        $originalContent = random_bytes(20 * 1024);
        $gzipped = gzencode($originalContent, 9);

        $response = new Response(
            $gzipped,
            200,
            [
                'Content-Type' => 'text/plain',
                'Content-Encoding' => 'gzip',
            ]
        );

        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip, br']);
        $result = runCompressResponseMiddleware($request, $response);

        // The Content-Encoding header should remain 'gzip'
        // (not changed to 'br' or duplicated)
        $contentEncoding = $result->headers->get('Content-Encoding');

        // EXPECTED ON UNFIXED CODE: May pass or fail depending on implementation
        // This documents the expected behavior: header should be preserved
        expect($contentEncoding)->toBe('gzip');
    })->group('bug-exploration', 'bug-3');
});

describe('Bug 4 - Quality Zero Handling', function (): void {

    it('should not compress when client explicitly refuses encoding with q=0', function (): void {
        // Configure gzip compression
        config()->set('response-compression.algorithm', 'gzip');
        config()->set('response-compression.min_length', 1024);

        // Create a compressible response that meets size requirements
        $originalContent = str_repeat('Test content for compression. ', 1000); // ~30KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        // Request with explicit gzip refusal (q=0)
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip;q=0']);

        // Apply compression via middleware
        $result = runCompressResponseMiddleware($request, $response);

        // Assert: No Content-Encoding header should be present
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug ignores q=0 and compresses anyway
        expect($result->headers->has('Content-Encoding'))->toBeFalse();

        // Assert: Response body should be uncompressed (matches original)
        expect($result->getContent())->toBe($originalContent);
    })->group('bug-exploration', 'bug-4');

    it('should not compress when wildcard is refused with q=0', function (): void {
        // Configure gzip compression
        config()->set('response-compression.algorithm', 'gzip');
        config()->set('response-compression.min_length', 1024);

        // Create a compressible response
        $originalContent = str_repeat('Wildcard refusal test. ', 1000); // ~23KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        // Request with wildcard refusal (all encodings refused)
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => '*;q=0']);

        // Apply compression via middleware
        $result = runCompressResponseMiddleware($request, $response);

        // Assert: No Content-Encoding header should be present
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug ignores wildcard q=0 and compresses anyway
        expect($result->headers->has('Content-Encoding'))->toBeFalse();

        // Assert: Response body should be uncompressed
        expect($result->getContent())->toBe($originalContent);
    })->group('bug-exploration', 'bug-4');

    it('should not compress when wildcard accepts but specific encoding is refused', function (): void {
        // Configure gzip compression
        config()->set('response-compression.algorithm', 'gzip');
        config()->set('response-compression.min_length', 1024);

        // Create a compressible response
        $originalContent = str_repeat('Override test content. ', 1000); // ~23KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        // Request with wildcard accepting but gzip explicitly refused
        // The specific q=0 should override the wildcard q=1
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => '*;q=1, gzip;q=0']);

        // Apply compression via middleware
        $result = runCompressResponseMiddleware($request, $response);

        // Assert: No Content-Encoding header should be present
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug ignores the gzip;q=0 override and compresses with gzip
        expect($result->headers->has('Content-Encoding'))->toBeFalse();

        // Assert: Response body should be uncompressed
        expect($result->getContent())->toBe($originalContent);
    })->group('bug-exploration', 'bug-4');
});

describe('Bug 5 - Partial Response Handling', function (): void {

    it('should not compress HTTP 206 partial responses with Content-Range header', function (): void {
        // Configure gzip compression
        config()->set('response-compression.algorithm', 'gzip');
        config()->set('response-compression.min_length', 1024);

        // Create 20KB of content for a range request scenario
        $fullContent = str_repeat('Partial content test. ', 900); // ~19.8KB
        $fullContentLength = strlen($fullContent);

        // Simulate a 206 Partial Content response
        // Client requested bytes 0-10999 (first 11KB) of a 20KB resource
        $rangeStart = 0;
        $rangeEnd = 10999;
        $partialContent = substr($fullContent, $rangeStart, $rangeEnd - $rangeStart + 1);
        $partialContentLength = strlen($partialContent);

        // Create a 206 response with Content-Range header
        $response = new Response(
            $partialContent,
            206, // HTTP 206 Partial Content
            [
                'Content-Type' => 'text/plain',
                'Content-Range' => "bytes {$rangeStart}-{$rangeEnd}/{$fullContentLength}",
                'Content-Length' => (string) $partialContentLength,
            ]
        );

        // Request with gzip accepted
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']);

        // Apply compression via middleware
        $result = runCompressResponseMiddleware($request, $response);

        // Assert: Content-Range header should be unchanged
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug compresses the body but leaves Content-Range unchanged,
        // creating a mismatch between the advertised byte range and actual body size
        $resultContentRange = $result->headers->get('Content-Range');
        expect($resultContentRange)->toBe("bytes {$rangeStart}-{$rangeEnd}/{$fullContentLength}");

        // Assert: No Content-Encoding header should be present
        // (indicating the response was NOT compressed)
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug compresses the response and adds Content-Encoding header
        expect($result->headers->has('Content-Encoding'))->toBeFalse();

        // Assert: Body size should match the Content-Range size (11KB, not compressed)
        $resultBody = $result->getContent();
        $resultBodyLength = strlen($resultBody);

        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug compresses the body to ~73 bytes while Content-Range still claims 11KB
        expect($resultBodyLength)->toBe($partialContentLength)
            ->and($resultBody)->toBe($partialContent);
    })->group('bug-exploration', 'bug-5');

    it('should not compress responses with Content-Range header even with status 200', function (): void {
        // Edge case: Some servers might use Content-Range with status 200
        // (though this is non-standard, it should still be skipped)
        config()->set('response-compression.algorithm', 'gzip');
        config()->set('response-compression.min_length', 1024);

        // Create partial content
        $content = str_repeat('Edge case content. ', 1000); // ~19KB
        $contentLength = strlen($content);

        // Create response with status 200 but Content-Range header present
        $response = new Response(
            $content,
            200, // Status 200 instead of 206
            [
                'Content-Type' => 'text/plain',
                'Content-Range' => "bytes 0-{$contentLength}/{$contentLength}",
            ]
        );

        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']);
        $result = runCompressResponseMiddleware($request, $response);

        // Assert: Presence of Content-Range header alone should prevent compression
        // EXPECTED ON UNFIXED CODE: FAILS
        // Bug may compress because status is 200 (successful)
        expect($result->headers->has('Content-Encoding'))->toBeFalse();
        expect($result->getContent())->toBe($content);
    })->group('bug-exploration', 'bug-5');

    it('should preserve byte range accuracy by skipping compression on 206 responses', function (): void {
        // This test specifically checks that byte range semantics are preserved
        config()->set('response-compression.algorithm', 'gzip');
        config()->set('response-compression.min_length', 1024);

        // Create a large content where we can meaningfully test a range
        $fullContent = random_bytes(20 * 1024); // 20KB of random data
        $fullContentLength = strlen($fullContent);

        // Request middle portion: bytes 5000-14999 (10KB)
        $rangeStart = 5000;
        $rangeEnd = 14999;
        $partialContent = substr($fullContent, $rangeStart, $rangeEnd - $rangeStart + 1);

        $response = new Response(
            $partialContent,
            206,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Range' => "bytes {$rangeStart}-{$rangeEnd}/{$fullContentLength}",
            ]
        );

        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']);
        $result = runCompressResponseMiddleware($request, $response);

        // Assert: The response body should be exactly the partial content
        // (not compressed, which would corrupt the byte range)
        $resultBody = $result->getContent();

        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug compresses the body, making it no longer match the byte range
        expect($resultBody)->toBe($partialContent);

        // Assert: Content-Range should accurately describe the body
        $resultContentRange = $result->headers->get('Content-Range');
        expect($resultContentRange)->toBe("bytes {$rangeStart}-{$rangeEnd}/{$fullContentLength}");

        // Verify the body is exactly the size promised by Content-Range
        $promisedSize = $rangeEnd - $rangeStart + 1;
        expect(strlen($resultBody))->toBe($promisedSize);
    })->group('bug-exploration', 'bug-5');
});

describe('Bug 6 - Fallback Gating', function (): void {

    it('should fallback to accepted encoding when algorithm config does not match', function (): void {
        // Configure multiple encodings mode with algorithm=br but encodingOrder includes gzip
        config()->set('response-compression.algorithm', 'br');
        config()->set('response-compression.try_multiple_encodings', true);
        config()->set('response-compression.multiple_encodings_order', 'gzip,br');
        config()->set('response-compression.min_length', 1024);

        // Create a compressible response that meets size requirements
        $originalContent = str_repeat('Fallback gating test content. ', 1000); // ~30KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        // Request with gzip accepted but NOT brotli
        // The algorithm config says 'br' but the client only accepts 'gzip'
        // The encodingOrder says gzip is available as a fallback
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']);

        // Apply compression via middleware
        $result = runCompressResponseMiddleware($request, $response);

        // Assert: Response should have Content-Encoding header with gzip
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug validates against algorithm=br, sees it's not accepted,
        // and blocks compression entirely (validateRequest() fails)
        // Result: no Content-Encoding header, uncompressed response
        expect($result->headers->has('Content-Encoding'))->toBeTrue();

        $contentEncoding = $result->headers->get('Content-Encoding');
        expect($contentEncoding)->toBe('gzip');

        // Assert: Response body should be gzip-compressed
        $compressedBody = $result->getContent();

        // EXPECTED ON UNFIXED CODE: FAILS
        // Body will be uncompressed, matching original content
        // After fix: body will be gzip-compressed
        expect($compressedBody)->not->toBe($originalContent);

        // Verify the body is actually gzip-compressed and decompresses correctly
        $decompressed = gzdecode($compressedBody);
        expect($decompressed)->toBe($originalContent);
    })->group('bug-exploration', 'bug-6');

    it('should use fallback encoding when primary algorithm is not accepted', function (): void {
        // Another variant: test with different encoding order
        config()->set('response-compression.algorithm', 'zstd');
        config()->set('response-compression.try_multiple_encodings', true);
        config()->set('response-compression.multiple_encodings_order', 'br,gzip,zstd');
        config()->set('response-compression.min_length', 1024);

        $originalContent = str_repeat('Test multiple fallback. ', 1000); // ~24KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        // Client accepts only gzip (neither zstd nor br)
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']);

        $result = runCompressResponseMiddleware($request, $response);

        // Should still compress with gzip even though algorithm=zstd
        // EXPECTED ON UNFIXED CODE: FAILS
        // Bug blocks compression because algorithm=zstd doesn't match
        expect($result->headers->has('Content-Encoding'))->toBeTrue();
        expect($result->headers->get('Content-Encoding'))->toBe('gzip');

        $compressedBody = $result->getContent();
        expect($compressedBody)->not->toBe($originalContent);

        $decompressed = gzdecode($compressedBody);
        expect($decompressed)->toBe($originalContent);
    })->group('bug-exploration', 'bug-6');

    it('should negotiate from candidate list not single algorithm setting', function (): void {
        // Test that demonstrates the root cause: negotiation should use the candidate list
        // not the single algorithm config when multiple_encodings is enabled
        config()->set('response-compression.algorithm', 'br');
        config()->set('response-compression.try_multiple_encodings', true);
        config()->set('response-compression.multiple_encodings_order', 'gzip,br,zstd');
        config()->set('response-compression.min_length', 1024);

        $originalContent = str_repeat('Negotiation test. ', 1500); // ~27KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'application/json',
            ]
        );

        // Client accepts gzip and zstd (not br)
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip, zstd']);

        $result = runCompressResponseMiddleware($request, $response);

        // Should compress with gzip (first in order that client accepts)
        // EXPECTED ON UNFIXED CODE: FAILS
        // Bug uses algorithm=br for validation, fails, returns uncompressed
        expect($result->headers->has('Content-Encoding'))->toBeTrue();

        $contentEncoding = $result->headers->get('Content-Encoding');
        expect($contentEncoding)->toBeIn(['gzip', 'zstd']); // Either is acceptable

        // Verify compression worked
        $compressedBody = $result->getContent();
        expect($compressedBody)->not->toBe($originalContent);

        // Verify decompression
        $decompressed = match ($contentEncoding) {
            'gzip' => gzdecode($compressedBody),
            'zstd' => zstd_uncompress($compressedBody),
            default => throw new RuntimeException("Unexpected encoding: {$contentEncoding}"),
        };

        expect($decompressed)->toBe($originalContent);
    })->group('bug-exploration', 'bug-6');
});

describe('Bug 7 - User-Agent Variation', function (): void {

    it('should include User-Agent in Vary header when User-Agent affects compression decision', function (): void {
        // Configure brotli with User-Agent skip list
        config()->set('response-compression.algorithm', 'br');
        config()->set('response-compression.min_length', 1024);
        config()->set('response-compression.br.non_supporting_user_agent_prefixes', ['axios']);

        // Create a compressible response
        $originalContent = str_repeat('User-Agent variation test. ', 1000); // ~27KB

        // Request A: axios User-Agent (should be refused brotli)
        $responseA = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        $requestA = Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_ENCODING' => 'br',
            'HTTP_USER_AGENT' => 'axios/1.7.2',
        ]);

        $resultA = runCompressResponseMiddleware($requestA, $responseA);

        // Request B: Mozilla User-Agent (should get brotli)
        $responseB = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        $requestB = Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_ENCODING' => 'br',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ]);

        $resultB = runCompressResponseMiddleware($requestB, $responseB);

        // Assert: BOTH responses should have Vary: Accept-Encoding, User-Agent
        // Because User-Agent affects the compression decision, shared caches need
        // to know that different User-Agents might receive different representations

        $varyHeaderA = $resultA->headers->get('Vary');
        dump('Vary A: '.$varyHeaderA);
        $varyHeaderB = $resultB->headers->get('Vary');
        dump('Vary B: '.$varyHeaderB);

        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug causes both responses to have only "Vary: Accept-Encoding"
        // without including "User-Agent", even though User-Agent affected the decision
        expect($varyHeaderA)->toContain('Accept-Encoding')
            ->and($varyHeaderA)->toContain('User-Agent');

        expect($varyHeaderB)->toContain('Accept-Encoding')
            ->and($varyHeaderB)->toContain('User-Agent');

        // Additional verification: Request A should NOT be compressed
        expect($resultA->headers->has('Content-Encoding'))->toBeFalse();
        expect($resultA->getContent())->toBe($originalContent);

        // Additional verification: Request B SHOULD be compressed
        expect($resultB->headers->has('Content-Encoding'))->toBeTrue();
        expect($resultB->headers->get('Content-Encoding'))->toBe('br');
        expect($resultB->getContent())->not->toBe($originalContent);
    })->group('bug-exploration', 'bug-7');
});

describe('Bug 8 - ETag Handling', function (): void {

    it('should handle strong ETags when compressing responses', function (): void {
        // Configure gzip compression
        config()->set('response-compression.algorithm', 'gzip');
        config()->set('response-compression.min_length', 1024);

        // Create 11KB response with strong ETag (no W/ prefix)
        $originalContent = str_repeat('ETag handling test content. ', 400); // ~11.2KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
                'ETag' => '"original-tag"', // Strong ETag
            ]
        );

        // Request with gzip accepted
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']);

        // Apply compression via middleware
        $result = runCompressResponseMiddleware($request, $response);

        // Get the ETag header from the result
        $resultETag = $result->headers->get('ETag');

        // Assert: When compression changes the response body, the strong ETag must be:
        // 1. Removed (no ETag header), OR
        // 2. Weakened (starts with W/), OR
        // 3. Changed to a different value
        //
        // It MUST NOT remain as the original strong ETag "original-tag"
        // because the compressed body is different bytes than the original
        //
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug leaves the ETag unchanged at "original-tag" despite the body
        // being compressed, which violates HTTP validator semantics

        // The ETag should be either:
        // - null (removed)
        // - starts with 'W/' (weakened)
        // - a different value than the original
        $isETagValid = $resultETag === null ||
                       str_starts_with($resultETag, 'W/') ||
                       $resultETag !== '"original-tag"';

        expect($isETagValid)->toBeTrue(
            'ETag should be removed, weakened, or changed after compression. '.
            'Got: '.($resultETag ?? 'null')
        );

        // Additional verification: Ensure compression actually happened
        expect($result->headers->has('Content-Encoding'))->toBeTrue();
        expect($result->headers->get('Content-Encoding'))->toBe('gzip');
        expect($result->getContent())->not->toBe($originalContent);
    })->group('bug-exploration', 'bug-8');
});

describe('Bug 9 - Case Normalization', function (): void {

    it('should match uppercase encoding names case-insensitively', function (): void {
        // Configure gzip compression
        config()->set('response-compression.algorithm', 'gzip');
        config()->set('response-compression.min_length', 1024);

        // Create a compressible response
        $originalContent = str_repeat('Case normalization test. ', 1000); // ~25KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        // Request with UPPERCASE encoding name
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'GZIP']);

        // Apply compression via middleware
        $result = runCompressResponseMiddleware($request, $response);

        // Assert: Response should have Content-Encoding: gzip
        // HTTP header values should be case-insensitive per RFC 7231
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug performs case-sensitive comparison, so "GZIP" doesn't match "gzip"
        expect($result->headers->has('Content-Encoding'))->toBeTrue();
        expect($result->headers->get('Content-Encoding'))->toBe('gzip');

        // Assert: Response body should be compressed
        $compressedBody = $result->getContent();

        // EXPECTED ON UNFIXED CODE: FAILS
        // Body will be uncompressed because the encoding match failed
        expect($compressedBody)->not->toBe($originalContent);

        // Verify the body is actually gzip-compressed and decompresses correctly
        $decompressed = gzdecode($compressedBody);
        expect($decompressed)->toBe($originalContent);
    })->group('bug-exploration', 'bug-9');

    it('should match title-case encoding name Gzip', function (): void {
        // Test with title case
        config()->set('response-compression.algorithm', 'gzip');
        config()->set('response-compression.min_length', 1024);

        $originalContent = str_repeat('Mixed case test. ', 1000); // ~17KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        // Request with title case encoding name "Gzip"
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'Gzip']);

        $result = runCompressResponseMiddleware($request, $response);

        // EXPECTED ON UNFIXED CODE: FAILS
        // Case-sensitive comparison rejects "Gzip" vs "gzip"
        expect($result->headers->has('Content-Encoding'))->toBeTrue();
        expect($result->headers->get('Content-Encoding'))->toBe('gzip');

        $compressedBody = $result->getContent();
        expect($compressedBody)->not->toBe($originalContent);

        $decompressed = gzdecode($compressedBody);
        expect($decompressed)->toBe($originalContent);
    })->group('bug-exploration', 'bug-9');

    it('should match camel-case encoding name GZip', function (): void {
        // Test with another mixed case variant
        config()->set('response-compression.algorithm', 'gzip');
        config()->set('response-compression.min_length', 1024);

        $originalContent = str_repeat('GZip test content. ', 1000); // ~19KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        // Request with camel-case encoding name "GZip"
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'GZip']);

        $result = runCompressResponseMiddleware($request, $response);

        // EXPECTED ON UNFIXED CODE: FAILS
        // Case-sensitive comparison rejects "GZip" vs "gzip"
        expect($result->headers->has('Content-Encoding'))->toBeTrue();
        expect($result->headers->get('Content-Encoding'))->toBe('gzip');

        $compressedBody = $result->getContent();
        expect($compressedBody)->not->toBe($originalContent);

        $decompressed = gzdecode($compressedBody);
        expect($decompressed)->toBe($originalContent);
    })->group('bug-exploration', 'bug-9');

    it('should match uppercase BR encoding name', function (): void {
        // Test with brotli encoding (uppercase)
        config()->set('response-compression.algorithm', 'br');
        config()->set('response-compression.min_length', 1024);

        $originalContent = str_repeat('Brotli case test. ', 1000); // ~18KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        // Request with uppercase BR
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'BR']);

        $result = runCompressResponseMiddleware($request, $response);

        // EXPECTED ON UNFIXED CODE: FAILS
        // Case-sensitive comparison rejects "BR" vs "br"
        expect($result->headers->has('Content-Encoding'))->toBeTrue();
        expect($result->headers->get('Content-Encoding'))->toBe('br');

        $compressedBody = $result->getContent();
        expect($compressedBody)->not->toBe($originalContent);

        $decompressed = brotli_uncompress($compressedBody);
        expect($decompressed)->toBe($originalContent);
    })->group('bug-exploration', 'bug-9');

    it('should match mixed-case encodings in multi-encoding mode', function (): void {
        // Test case normalization with multiple encodings
        config()->set('response-compression.try_multiple_encodings', true);
        config()->set('response-compression.multiple_encodings_order', 'gzip,br');
        config()->set('response-compression.min_length', 1024);

        $originalContent = str_repeat('Multi-encoding case test. ', 1000); // ~26KB

        $response = new Response(
            $originalContent,
            200,
            [
                'Content-Type' => 'text/plain',
            ]
        );

        // Request with mixed-case encoding names
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'GZIP, Br']);

        $result = runCompressResponseMiddleware($request, $response);

        // EXPECTED ON UNFIXED CODE: FAILS
        // Case-sensitive comparison fails to match any encoding
        expect($result->headers->has('Content-Encoding'))->toBeTrue();

        $contentEncoding = $result->headers->get('Content-Encoding');
        expect($contentEncoding)->toBeIn(['gzip', 'br']);

        $compressedBody = $result->getContent();
        expect($compressedBody)->not->toBe($originalContent);

        // Verify decompression based on which encoding was used
        $decompressed = match ($contentEncoding) {
            'gzip' => gzdecode($compressedBody),
            'br' => brotli_uncompress($compressedBody),
            default => throw new RuntimeException("Unexpected encoding: {$contentEncoding}"),
        };

        expect($decompressed)->toBe($originalContent);
    })->group('bug-exploration', 'bug-9');
});

describe('Bug 10 - Brotli Detection', function (): void {

    it('should detect valid brotli-compressed empty string', function (): void {
        // Create a valid brotli-compressed empty string
        $emptyString = '';
        $compressedEmpty = brotli_compress($emptyString);

        // Verify that brotli_uncompress successfully decompresses it
        $uncompressed = brotli_uncompress($compressedEmpty);
        expect($uncompressed)->toBe($emptyString);

        // Assert: isBrotliEncoded() should return true for valid compressed content
        // Even though the decompressed result is an empty string, the compression
        // is valid and decompression succeeded (returned not false)
        //
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug specifically rejects empty string results:
        // `return $decoded !== false && $decoded !== '' && $decoded !== '0';`
        // This incorrectly treats valid compressed empty content as invalid
        $isDetected = Uak35\ResponseCompression\Support\Enc::isBrotliEncoded($compressedEmpty);

        expect($isDetected)->toBeTrue(
            'isBrotliEncoded() should return true for valid brotli-compressed empty string. '.
            'brotli_uncompress() succeeded, so the content is valid brotli.'
        );
    })->group('bug-exploration', 'bug-10');

    it('should detect valid brotli-compressed zero string', function (): void {
        // Create a valid brotli-compressed string containing '0'
        $zeroString = '0';
        $compressedZero = brotli_compress($zeroString);

        // Verify that brotli_uncompress successfully decompresses it
        $uncompressed = brotli_uncompress($compressedZero);
        expect($uncompressed)->toBe($zeroString);

        // Assert: isBrotliEncoded() should return true for valid compressed content
        // Even though the decompressed result is the string '0', the compression
        // is valid and decompression succeeded (returned not false)
        //
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug specifically rejects '0' string results:
        // `return $decoded !== false && $decoded !== '' && $decoded !== '0';`
        // This incorrectly treats valid compressed '0' content as invalid
        $isDetected = Uak35\ResponseCompression\Support\Enc::isBrotliEncoded($compressedZero);

        expect($isDetected)->toBeTrue(
            "isBrotliEncoded() should return true for valid brotli-compressed '0' string. ".
            'brotli_uncompress() succeeded, so the content is valid brotli.'
        );
    })->group('bug-exploration', 'bug-10');

    it('should distinguish successful decompression from decompression failure', function (): void {
        // This test verifies the core issue: the function should return true
        // when brotli_uncompress() returns anything except false

        // Valid compressed empty string
        $validEmpty = brotli_compress('');

        // Valid compressed zero string
        $validZero = brotli_compress('0');

        // Invalid brotli data (random bytes that will fail decompression)
        // Use a simple string that's not valid brotli but won't cause exceptions
        $invalidData = 'This is not valid brotli compressed data';

        // Test valid empty: decompression succeeds, returns ''
        $uncompressedEmpty = brotli_uncompress($validEmpty);
        expect($uncompressedEmpty)->toBe('');
        expect($uncompressedEmpty)->not->toBe(false);

        // Test valid zero: decompression succeeds, returns '0'
        $uncompressedZero = brotli_uncompress($validZero);
        expect($uncompressedZero)->toBe('0');
        expect($uncompressedZero)->not->toBe(false);

        // Test invalid: decompression fails, returns false (or throws exception caught by Enc::isBrotliEncoded)
        // We don't call brotli_uncompress directly here since it may throw
        // Instead, we rely on Enc::isBrotliEncoded to handle it

        // Assert: isBrotliEncoded() should distinguish between:
        // 1. Decompression succeeded with empty result → true (valid brotli)
        // 2. Decompression succeeded with '0' result → true (valid brotli)
        // 3. Decompression failed → false (invalid brotli)
        //
        // EXPECTED ON UNFIXED CODE: FAILS
        // The bug treats cases 1 and 2 the same as case 3, returning false for all
        $isValidEmptyDetected = Uak35\ResponseCompression\Support\Enc::isBrotliEncoded($validEmpty);
        $isValidZeroDetected = Uak35\ResponseCompression\Support\Enc::isBrotliEncoded($validZero);
        $isInvalidDetected = Uak35\ResponseCompression\Support\Enc::isBrotliEncoded($invalidData);

        expect($isValidEmptyDetected)->toBeTrue('Valid compressed empty string should be detected as brotli');
        expect($isValidZeroDetected)->toBeTrue('Valid compressed zero string should be detected as brotli');
        expect($isInvalidDetected)->toBeFalse('Invalid data should NOT be detected as brotli');
    })->group('bug-exploration', 'bug-10');

    it('should handle edge case of compressed whitespace strings', function (): void {
        // Additional edge case: other "falsy-looking" but valid content

        // Test various whitespace and special strings
        $testCases = [
            '',           // empty
            '0',          // zero character
            ' ',          // single space
            "\n",         // newline
            "\t",         // tab
            'false',      // string 'false'
            'null',       // string 'null'
        ];

        foreach ($testCases as $content) {
            $compressed = brotli_compress($content);
            $decompressed = brotli_uncompress($compressed);

            // Verify decompression succeeds
            expect($decompressed)->toBe($content)
                ->and($decompressed)->not->toBe(false);

            // Assert: All should be detected as valid brotli
            // EXPECTED ON UNFIXED CODE: FAILS for '' and '0' cases
            // Bug specifically rejects these two cases
            $isDetected = Uak35\ResponseCompression\Support\Enc::isBrotliEncoded($compressed);

            expect($isDetected)->toBeTrue(
                'isBrotliEncoded() should detect valid brotli for content: '.json_encode($content)
            );
        }
    })->group('bug-exploration', 'bug-10');
});

describe('Bug 11 - Test Fixtures', function (): void {

    it('demonstrates pre-compressed test fixture compresses below min_length threshold', function (): void {
        // This test examines the existing pre-compressed test fixture to show it doesn't
        // exercise the claimed guard behavior.
        //
        // The test claims to verify that pre-compressed content is not compressed again.
        // However, the fixture used (getLongContent()) compresses below the min_length
        // threshold of 2048 bytes, so the guard is never actually exercised.

        // Get the fixture used in the pre-compressed test
        $fixture = getLongContent(); // str_repeat('Hello World', 1000)

        // Examine uncompressed fixture size
        $uncompressedSize = strlen($fixture);
        expect($uncompressedSize)->toBeGreaterThan(0);

        // Compress the fixture with maximum compression (what the test does)
        $compressed = gzencode($fixture, 9);
        $compressedSize = strlen($compressed);

        // Get the min_length configuration (default 2048 bytes)
        $minLength = config('response-compression.min_length', 2048);

        // Assert: The compressed fixture should exceed min_length to exercise the guard
        // EXPECTED ON UNFIXED CODE: FAILS
        // The fixture compresses to less than 2048 bytes, so even if the middleware
        // tried to compress it again, it would be skipped due to size constraints.
        // The test passes for the wrong reason - the size check prevents double
        // compression, not the pre-compression detection logic.
        expect($compressedSize)->toBeGreaterThan($minLength,
            "Pre-compressed test fixture compresses to {$compressedSize} bytes, ".
            "below min_length threshold of {$minLength} bytes. ".
            "This means the test doesn't exercise the pre-compression detection guard - ".
            'it passes because the size check prevents compression, not because the '.
            'Content-Encoding header is detected.'
        );
    })->group('bug-exploration', 'bug-11');

    it('demonstrates first-candidate test does not offer multiple accepted encodings', function (): void {
        // This test examines tests that claim to exercise first-candidate selection
        // (choosing the first encoding from a list when multiple are accepted).
        //
        // Search the test suite for tests that supposedly test multiple encoding selection.
        // The bug is that these tests only offer ONE accepted encoding, so there's no
        // actual selection happening - the middleware just uses the only option available.

        // Simulate what a "first candidate" test should do:
        // 1. Configure multiple encodings in order
        config()->set('response-compression.try_multiple_encodings', true);
        config()->set('response-compression.multiple_encodings_order', 'gzip,br,zstd');
        config()->set('response-compression.min_length', 1024);

        // 2. Create content that will compress
        $content = str_repeat('First candidate test content. ', 200); // ~6KB
        $response = new Response($content, 200, ['Content-Type' => 'text/plain']);

        // 3. The bug: tests that claim to verify first-candidate selection only accept ONE encoding
        // Example: Accept-Encoding: gzip
        // This doesn't test selection because there's nothing to select from!
        $requestSingleEncoding = Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ]);

        $resultSingleEncoding = runCompressResponseMiddleware($requestSingleEncoding, $response);

        // With only one accepted encoding, the middleware has no choice
        expect($resultSingleEncoding->headers->get('Content-Encoding'))->toBe('gzip');

        // 4. To ACTUALLY test first-candidate selection, the test needs MULTIPLE accepted encodings
        // Example: Accept-Encoding: gzip, br, zstd
        $requestMultipleEncodings = Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_ENCODING' => 'gzip, br, zstd',
        ]);

        $response2 = new Response($content, 200, ['Content-Type' => 'text/plain']);
        $resultMultipleEncodings = runCompressResponseMiddleware($requestMultipleEncodings, $response2);

        // Now the middleware must SELECT which encoding to use
        // It should pick the first one in the configured order that the client accepts
        // In this case: gzip (first in 'gzip,br,zstd' order)
        $selectedEncoding = $resultMultipleEncodings->headers->get('Content-Encoding');

        // Assert: The test suite should include tests where multiple encodings are accepted
        // EXPECTED: This assertion documents what SHOULD be tested
        // The existing tests only accept one encoding at a time, never exercising
        // the selection logic that picks the first from multiple candidates.
        expect($selectedEncoding)->toBe('gzip',
            'First-candidate selection test should accept MULTIPLE encodings '.
            "(e.g., 'gzip, br, zstd') to exercise the selection logic. ".
            "Tests that only accept a single encoding (e.g., 'gzip' alone) ".
            "don't test selection - they just test that the middleware can ".
            'compress with that one encoding.'
        );
    })->group('bug-exploration', 'bug-11');

    it('demonstrates vary-preservation test requests identity encoding', function (): void {
        // This test examines tests that claim to verify Vary header preservation.
        // The bug is that these tests request 'identity' encoding (or no compression),
        // so the encoder never runs and never has the opportunity to overwrite Vary headers.
        // The test passes because no compression happens, not because Vary is preserved.

        // Example of what the buggy test might do:
        // 1. Create response with existing Vary header
        $content = getLongContent();
        $response = new Response($content, 200, [
            'Content-Type' => 'text/plain',
            'Vary' => 'Origin',
        ]);

        // 2. The bug: test requests 'identity' (no compression)
        $requestIdentity = Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_ENCODING' => 'identity',
        ]);

        $resultIdentity = runCompressResponseMiddleware($requestIdentity, $response);

        // With identity encoding, no compression happens
        expect($resultIdentity->headers->has('Content-Encoding'))->toBeFalse(
            "Request with 'identity' encoding should not compress"
        );

        // The Vary header is preserved... but only because no encoder ran!
        // This doesn't test the Vary preservation logic at all.
        $varyHeader = $resultIdentity->headers->get('Vary');
        expect($varyHeader)->toContain('Origin');
        // Note: When identity is requested, middleware adds Accept-Encoding to Vary
        // but encoders never run, so this doesn't test encoder Vary handling

        // 3. To ACTUALLY test Vary preservation, compression must be applied
        $response2 = new Response($content, 200, [
            'Content-Type' => 'text/plain',
            'Vary' => 'Origin',
        ]);

        $requestWithCompression = Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ]);

        $resultWithCompression = runCompressResponseMiddleware($requestWithCompression, $response2);

        // Now compression actually happens and the encoder runs
        expect($resultWithCompression->headers->has('Content-Encoding'))->toBeTrue(
            "Request with 'gzip' encoding should compress"
        );
        expect($resultWithCompression->headers->get('Content-Encoding'))->toBe('gzip');

        // Assert: Test if Vary header is preserved when compression ACTUALLY occurs
        // EXPECTED ON UNFIXED CODE: This would FAIL due to Bug 1
        // The encoder's headers->add() call replaces Vary instead of appending
        $varyWithCompression = $resultWithCompression->headers->get('Vary');

        // This documents what SHOULD happen (and what Bug 1 breaks):
        // Vary should contain BOTH Origin (original) and Accept-Encoding (added)
        expect($varyWithCompression)->toContain('Origin',
            "Vary preservation test should request compression (e.g., 'gzip'), ".
            "not 'identity'. Tests that request 'identity' never exercise the ".
            "encoder's Vary header logic because no compression occurs. ".
            'They pass for the wrong reason - the middleware preserves Vary '.
            "when skipping compression, but that doesn't test the encoder's behavior."
        );
        expect($varyWithCompression)->toContain('Accept-Encoding');
    })->group('bug-exploration', 'bug-11');
});

it('DEBUG: Check if config loads', function (): void {
    config()->set('response-compression.br.non_supporting_user_agent_prefixes', ['axios', 'test']);

    $prefixes = config('response-compression.br.non_supporting_user_agent_prefixes');

    dump('Config loaded: '.json_encode($prefixes));
    expect($prefixes)->toBe(['axios', 'test']);
});
