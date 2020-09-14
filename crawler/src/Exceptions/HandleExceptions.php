<?php

declare(strict_types=1);

namespace Longman\Crawler\Exceptions;

use ErrorException;
use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Throwable;

use function error_get_last;
use function error_reporting;
use function in_array;
use function is_null;
use function register_shutdown_function;
use function set_error_handler;
use function set_exception_handler;
use function str_repeat;

use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_ERROR;
use const E_PARSE;

class HandleExceptions
{
    public static ?string $reservedMemory;
    private LoggerInterface $logger;
    private ?HubInterface $sentry;

    public function __construct(LoggerInterface $logger, ?HubInterface $sentry)
    {
        self::$reservedMemory = str_repeat('x', 10240);
        $this->logger = $logger;
        $this->sentry = $sentry;

        error_reporting(-1);

        set_error_handler([$this, 'handleError']);

        set_exception_handler([$this, 'handleException']);

        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleError(int $level, string $message, string $file = '', int $line = 0, array $context = []): void
    {
        if (error_reporting() & $level) {
            throw new ErrorException($message, 0, $level, $file, $line);
        }
    }

    public function handleException(Throwable $e): void
    {
        try {
            self::$reservedMemory = null;

            $this->logger->error($e->getMessage(), ['exception' => $e]);

            if ($this->sentry) {
                $this->sentry->captureException($e);
            }
        } catch (Throwable $e) {
            //
        }

        dump($e);
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if (! is_null($error) && $this->isFatal($error['type'])) {
            $this->handleException($this->fatalErrorFromPhpError($error, 0));
        }
    }

    protected function fatalErrorFromPhpError(array $error, $traceOffset = null): FatalError
    {
        return new FatalError($error['message'], 0, $error, $traceOffset);
    }

    protected function isFatal($type): bool
    {
        return in_array($type, [E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_PARSE]);
    }
}
