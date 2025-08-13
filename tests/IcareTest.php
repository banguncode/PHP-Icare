<?php
require __DIR__ . '/../vendor/autoload.php';

use PHPIcare\Icare;


defined("JKN_API_CONSID") or define("JKN_API_CONSID", "your_consid");
defined("JKN_API_SECRET") or define("JKN_API_SECRET", "your_secret");
defined("JKN_API_USERKEY") or define("JKN_API_USERKEY", "your_userkey");

$icare = (new Icare())->init(JKN_API_CONSID, JKN_API_SECRET, JKN_API_USERKEY);

$data = [
    ['param' => '1234567890000000', 'kodedokter' => '12345'],
    ['param' => '0000012345678', 'kodedokter' => '67890'],
    // Add more test data as needed
];
$total = count($data);

if ($total > 0) {
    echo "Total data: {$total}\n";

    // Create logs directory if not exists
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/' . date('Y-m-d') . '.log';

    foreach ($data as $key => $row) {
        printf("%d/%d\n", $key + 1, $total);

        list($param, $kodedokter) = array_values($row);
        $param = preg_replace('/\D/', '', $param);

        $response = $icare->getIcareHistory($param, $kodedokter);

        $logMsg = '';
        if ($response) {
            if (is_int($response)) {
                $time = $response;
                while ($time > 0) {
                    echo "Retry in {$time} s\n";
                    sleep(1);
                    $time--;
                }
                $response = $icare->getIcareHistory($param, $kodedokter);
            }

            // Consent
            $verif = $icare->getCekVerifikasi3($response);
            $approval = $icare->postApprovalIC($response);

            $logMsg .= "Request {$param} - {$kodedokter} success!\n";
            $logMsg .= "[REFERER] {$response}\n";
            $logMsg .= "[VERIF] {$verif}\n";
            $logMsg .= "[APPROVAL] {$approval}\n";
        } else {
            $logMsg .= "Request {$param} - {$kodedokter} failed!\n";
        }

        $logMsg .= "\n";
        echo $logMsg;

        // Write to log file
        file_put_contents($logFile, $logMsg, FILE_APPEND);

        sleep(5);
    }

    echo "Done!";
}
