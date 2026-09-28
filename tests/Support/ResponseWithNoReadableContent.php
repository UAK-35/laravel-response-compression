<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use Override;
use Symfony\Component\HttpFoundation\Response;

/**
 * A response that reports a body it will not hand over.
 *
 * `Response::getContent()` is declared `string|false`, and the two types the framework ships
 * that answer `false` — `BinaryFileResponse` and `StreamedResponse` — are named and ruled out
 * before the middleware reads anything. This stands in for anything else that answers the same
 * way, so the guard behind those two is exercised rather than assumed: what must not happen is
 * a `false` reaching an encoder as if it were a body.
 */
final class ResponseWithNoReadableContent extends Response
{
    #[Override]
    public function getContent(): string|false
    {
        return false;
    }
}
