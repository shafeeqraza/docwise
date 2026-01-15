<?php

namespace App\Services\V1\Common;

use Illuminate\Support\Facades\Log as LogFacade;

class LogService
{
    /**
     * Log an emergency message to the logs.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function emergency(string $message, array $context = []): void
    {
        LogFacade::emergency($message, $context);
    }

    /**
     * Log an alert message to the logs.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function alert(string $message, array $context = []): void
    {
        LogFacade::alert($message, $context);
    }

    /**
     * Log a critical message to the logs.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        LogFacade::critical($message, $context);
    }

    /**
     * Log an error message to the logs.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        LogFacade::error($message, $context);
    }

    /**
     * Log a warning message to the logs.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        LogFacade::warning($message, $context);
    }

    /**
     * Log a notice message to the logs.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function notice(string $message, array $context = []): void
    {
        LogFacade::notice($message, $context);
    }

    /**
     * Log an informational message to the logs.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        LogFacade::info($message, $context);
    }

    /**
     * Log a debug message to the logs.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        LogFacade::debug($message, $context);
    }

    /**
     * Log a message to the logs.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void
    {
        LogFacade::log($level, $message, $context);
    }

    /**
     * Write to a specific channel.
     *
     * @param string $channel
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function channel(string $channel, string $level, string $message, array $context = []): void
    {
        LogFacade::channel($channel)->log($level, $message, $context);
    }
}
