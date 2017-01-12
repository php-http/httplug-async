<?php

namespace Http\Client\Async;

use AsyncInterop\Promise;
use Psr\Http\Message\RequestInterface;

interface HttpAsyncClient
{
    /**
     * Sends a PSR-7 request in an asynchronous way.
     *
     * Exceptions related to processing the request are available from the returned Promise.
     *
     * @param RequestInterface $request
     *
     * @return Promise An async interop Promise which resolves a PSR-7 Response or fails with an Http\Client\Exception.
     *
     * @throws \Exception If processing the request is impossible (eg. bad configuration).
     */
    public function sendAsyncRequest(RequestInterface $request);
}
