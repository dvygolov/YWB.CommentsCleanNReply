<?php
require_once __DIR__ . '/settings.php';
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
        bool $die = false
    ): void
    {
        $timestamp = gmdate('H:i:s');
        $logFile = self::getLogFilePath();
        $logMessage = "[{$timestamp}] [{$level}] {$msg}\n";

        file_put_contents($logFile, $logMessage, FILE_APPEND);

        if (Settings::$debug){
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
    
    public function viewLog(
        ?string $startDate = null, 
        ?string $endDate = null, 
        array $filters = ['Trace' => true, 'Info' => true, 'Warning' => true, 'Error' => true]
    ): string
    {
        if ($startDate === null) {
            $startDate = gmdate('Y-m-d');
        }
        if ($endDate === null) {
            $endDate = $startDate;
        }

        $logs = [];
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));

        foreach ($dateRange as $date) {
            $logPath = self::$baseLogDir . '/' . $date->format('Y-m-d') . '.log';
            if (file_exists($logPath)) {
                $dateHeader = "\n[" . $date->format('Y-m-d') . "] " . str_repeat('-', 70) . "\n";
                $content = file_get_contents($logPath);
                
                // Filter log content
                $filteredEntries = [];
                
                // Split by timestamp pattern to get complete log entries
                $entries = preg_split('/(?=\[\d{2}:\d{2}:\d{2}\])/', $content, -1, PREG_SPLIT_NO_EMPTY);
                
                foreach ($entries as $entry) {
                    if (empty(trim($entry))) continue;
                    
                    $shouldInclude = false;
                    foreach ($filters as $type => $enabled) {
                        if ($enabled && strpos($entry, "[$type]") !== false) {
                            $shouldInclude = true;
                            break;
                        }
                    }
                    
                    // Include entries without any type markers
                    if (!preg_match('/\[(Trace|Info|Warning|Error)\]/', $entry)) {
                        $shouldInclude = true;
                    }
                    
                    if ($shouldInclude) {
                        $filteredEntries[] = $entry;
                    }
                }
                
                if (!empty($filteredEntries)) {
                    $logs[] = $dateHeader . implode("", $filteredEntries);
                }
            }
        }

        if (empty($logs)) {
            return "No logs found for the selected date range.";
        }

        return implode("\n", $logs);
    }
}

?>