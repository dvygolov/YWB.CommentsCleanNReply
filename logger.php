<?php

class CommentsLogger
{
    private static $baseLogDir = __DIR__ . '/logs';

    private static function checkAndCreateDir(string $dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    private static function getLogFilePath()
    {
        $logDir = self::$baseLogDir;
        self::checkAndCreateDir($logDir);

        $logFile = $logDir . '/' . gmdate('Y-m-d') . '.log';
        return $logFile;
    }

    public static function log(
        string $msg,
        string $level = 'Trace',
        bool $die = false,
        bool $print = true
    )
    {
        $timestamp = gmdate('H:i:s');
        $logFile = self::getLogFilePath();
        $logMessage = "[{$timestamp}] [{$level}] {$msg}\n";

        file_put_contents($logFile, $logMessage, FILE_APPEND);

        if ($print){
            switch($level){
                case 'Error':
                    echo "<p style='color:red'>{$logMessage}</p>";
                    break;
                case 'Warning':
                    echo "<p style='color:lime'>{$logMessage}</p>";
                    break;
                case 'Info':
                    echo "<p style='color:navy'>{$logMessage}</p>";
                    break;
                default:
                    echo "<p>{$logMessage}</p>";
                    break;
            }
        }
        if ($die) die();
    }
}

?>