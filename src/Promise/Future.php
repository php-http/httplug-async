<?php

namespace Http\Client\Async\Promise;

use Http\Client\Exception;
use Interop\Async\Loop;
use Interop\Async\Promise;
use Psr\Http\Message\ResponseInterface;

class Future implements Promise
{
    const STATE_PENDING = 'pending';
    const STATE_SUCCEEDED = 'succeeded';
    const STATE_FAILED = 'failed';

    /** @var array An array of callable which will be called when the promise is resolved */
    private $callbackQueue = [];

    /** @var ResponseInterface response keeped in case of the promise is used after being resolved */
    private $response;

    /** @var Exception exception which is keeped in case of the promise is used after being resolved */
    private $exception;

    /** @var string Current state of the promise */
    private $state = self::STATE_PENDING;

    /**
     * Resolve the promise with a success : PSR 7 Response
     *
     * @param ResponseInterface $response
     *
     * @throws \Exception
     */
    public function success(ResponseInterface $response)
    {
        if ($this->state !== self::STATE_PENDING) {
            throw new \Exception('Promise already resolved');
        }

        $this->state = self::STATE_SUCCEEDED;
        $this->response = $response;

        foreach ($this->callbackQueue as $onResolved) {
            $this->resolve(function () use ($onResolved, $response) {
                $onResolved($response, null);
            });
        }
    }

    /**
     * Resolve the promise with a failure : Http\Client\Exception
     *
     * @param Exception $exception
     *
     * @throws \Exception
     */
    public function failure(Exception $exception)
    {
        if ($this->state !== self::STATE_PENDING) {
            throw new \Exception('Promise already resolved');
        }

        $this->state = self::STATE_FAILED;
        $this->exception = $exception;

        foreach ($this->callbackQueue as $onResolved) {
            $this->resolve(function () use ($onResolved, $exception) {
                $onResolved(null, $exception);
            });
        }
    }

    /**
     * Internal resolution
     *
     * @param $callback
     */
    private function resolve($callback) {
        try {
            $callback();
            // @Todo find a way to avoid using the loop as it force sync user to have an
            // event loop implementation
        } catch (\Throwable $exception) {
            Loop::defer(static function () use ($exception) {
                throw $exception;
            });
        } catch (\Exception $exception) {
            Loop::defer(static function () use ($exception) {
                throw $exception;
            });
        }
    }

    /**
     * {@inheritdoc}
     */
    public function when(callable $onResolved)
    {
        if ($this->state === self::STATE_PENDING) {
            $this->callbackQueue[] = $onResolved;
        }

        if ($this->state === self::STATE_SUCCEEDED) {
            $this->resolve(function () use ($onResolved) {
                $onResolved($this->response, null);
            });
        }

        if ($this->state === self::STATE_FAILED) {
            $this->resolve(function () use ($onResolved) {
                $onResolved(null, $this->exception);
            });
        }
    }
}
