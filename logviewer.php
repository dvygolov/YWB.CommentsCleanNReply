<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    exit("Not logged in");
}

require_once __DIR__ . '/logger.php';
$logger = new CommentsLogger();

$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d', strtotime('-7 days'));
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

// Get filter states from URL parameters or set defaults
$filters = [
    'Trace' => isset($_GET['trace']) ? $_GET['trace'] === '1' : true,
    'Info' => isset($_GET['info']) ? $_GET['info'] === '1' : true,
    'Warning' => isset($_GET['warning']) ? $_GET['warning'] === '1' : true,
    'Error' => isset($_GET['error']) ? $_GET['error'] === '1' : true
];

$log = $logger->viewLog($_GET['pageId'], $start, $end, $filters);

$styles = [
    'date' => 'color: #00ff00;font-weight:bold;',  // Green bold for date
    'timestamp' => 'color: #61afef;',  // Blue for timestamp
    'trace' => 'color: #808080;',      // Gray for trace
    'info' => 'color: #98c379;',       // Light green for info
    'warning' => 'color: #ffd700;',    // Yellow for warning
    'error' => 'color: #e06c75;font-weight:bold;',  // Red bold for error
    'errormsg' => 'color: #e06c75;'    // Red for error message
];

// Highlight dates
$log = preg_replace(
    '/(\[\d{4}-\d{2}-\d{2}\])/',
    '<span style="' . $styles['date'] . '">$1</span>',
    $log
);

// Highlight timestamps
$log = preg_replace(
    '/(\[\d{2}:\d{2}:\d{2}\])/',
    '<span style="' . $styles['timestamp'] . '">$1</span>',
    $log
);

// Highlight Info messages
$log = preg_replace(
    '/(\[Info\])/',
    '<span style="' . $styles['info'] . '">$1</span>',
    $log
);

// Highlight Warning messages
$log = preg_replace(
    '/(\[Warning\])/',
    '<span style="' . $styles['warning'] . '">$1</span>',
    $log
);

// Highlight Trace messages
$log = preg_replace(
    '/(\[Trace\])/',
    '<span style="' . $styles['trace'] . '">$1</span>',
    $log
);

// Highlight Error messages
$log = preg_replace(
    '/(\[Error\])(.*)/',
    '<span style="' . $styles['error'] . '">$1</span><span style="' . $styles['errormsg'] . '">$2</span>',
    $log
);
?>
<html>
<head>
    <title>Comments Clean'N'Reply Log Viewer</title>
    <link rel="icon" type="image/png" href="favicon.png" />
    <link rel="apple-touch-icon" href="favicon.png" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .hljs {
        padding: 15px;
        background: #282c34;
        color: #abb2bf;
        font-family: monospace;
        font-size: 16px;
        border-radius: 5px;
        overflow: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
        margin-top: 20px;
    }
    .date-picker-container {
        padding: 20px;
        background: #f8f9fa;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    #daterange {
        min-width: 300px;
    }
    </style>
</head>
<body>
    <div class="container">
        <div class="date-picker-container">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" id="daterange" name="daterange" class="form-control" />
                </div>
                <div class="col-md-6">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="traceCheck" value="Trace" <?= $filters['Trace'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="traceCheck">Trace</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="infoCheck" value="Info" <?= $filters['Info'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="infoCheck">Info</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="warningCheck" value="Warning" <?= $filters['Warning'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="warningCheck">Warning</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="errorCheck" value="Error" <?= $filters['Error'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="errorCheck">Error</label>
                    </div>
                </div>
            </div>
        </div>
        <div id="logContent">
            <pre class="hljs"><?=$log?></pre>
        </div>
    </div>

    <script type="text/javascript" src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
    $(function() {
        function updateFilters() {
            const baseUrl = window.location.pathname;
            const start = $('input[name="daterange"]').data('daterangepicker').startDate.format('YYYY-MM-DD');
            const end = $('input[name="daterange"]').data('daterangepicker').endDate.format('YYYY-MM-DD');
            const trace = $('#traceCheck').is(':checked') ? '1' : '0';
            const info = $('#infoCheck').is(':checked') ? '1' : '0';
            const warning = $('#warningCheck').is(':checked') ? '1' : '0';
            const error = $('#errorCheck').is(':checked') ? '1' : '0';
            
            window.location.href = `${baseUrl}?pixelId=<?=$_GET['pixelId']?>&uid=<?=$_GET['uid']?>&start=${start}&end=${end}&trace=${trace}&info=${info}&warning=${warning}&error=${error}`;
        }

        // Add event listeners to checkboxes
        $('#traceCheck, #infoCheck, #warningCheck, #errorCheck').change(updateFilters);

        $('input[name="daterange"]').daterangepicker({
            opens: 'left',
            startDate: moment('<?=$start?>'),
            endDate: moment('<?=$end?>'),
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            },
            locale: {
                format: 'YYYY-MM-DD',
                applyLabel: 'Apply',
                cancelLabel: 'Cancel',
                customRangeLabel: 'Custom Range'
            }
        }, function(start, end, label) {
            updateFilters();
        });
    });
    </script>
</body>
</html>
