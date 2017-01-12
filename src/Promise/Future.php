<?php

namespace Http\Client\Async\Promise;

use AsyncInterop\Promise;
use Http\Client\Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * 
 */
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
     * @throws \Exception|\Throwable
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
                $onResolved(null, $response);
            });
        }

        $this->callbackQueue = [];
    }

    /**
     * Resolve the promise with a failure : Http\Client\Exception
     *
     * @param Exception $exception
     *
     * @throws \Exception|\Throwable
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
                $onResolved($exception, null);
            });
        }

        $this->callbackQueue = [];
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
                $onResolved(null, $this->response);
            });
        }

        if ($this->state === self::STATE_FAILED) {
            $this->resolve(function () use ($onResolved) {
                $onResolved($this->exception, null);
            });
        }
    }

    /**
     * Internal resolution
     *
     * @param $callback
     *
     * @throws \Exception|\Throwable
     */
    private function resolve($callback) {
        try {
            $callback();
        } catch (\Throwable $exception) {
            Promise\ErrorHandler::notify($exception);
        } catch (\Exception $exception) {
            Promise\ErrorHandler::notify($exception);
        }
    }
}
