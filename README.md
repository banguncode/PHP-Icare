# PHP Icare

A simple PHP library for accessing the healthcare history of **JKN (Jaminan Kesehatan Nasional)** participants via **BPJS Kesehatan Icare API**.  
This library also supports fetching verification and approval data from the **Mobile Faskes** API.

---

## Features
- Access healthcare history of JKN participants by **NIK** or **BPJS Participant Number**.
- Automatic API signature generation (HMAC-SHA256).
- Response decryption using **AES-256-CBC** and **LZString** compression.
- Retrieve **verification** and **approval** status from Mobile Faskes.
- Retry mechanism for API responses with delay instructions.
- Simple logging example included.

---

## Requirements
- PHP >= 5.5
- [Composer](https://getcomposer.org/)
- PHP extensions:
  - `ext-curl`
  - `ext-openssl`

---

## Installation
Install via Composer:

```bash
composer require banguncode/php-icare
```

## Usage Example
```php
<?php
require __DIR__ . '/vendor/autoload.php';

use PHPIcare\Icare;

// Set your BPJS API credentials
defined("JKN_API_CONSID")  or define("JKN_API_CONSID", "your_consid");
defined("JKN_API_SECRET")  or define("JKN_API_SECRET", "your_secret");
defined("JKN_API_USERKEY") or define("JKN_API_USERKEY", "your_userkey");

// Initialize
$icare = (new Icare())->init(JKN_API_CONSID, JKN_API_SECRET, JKN_API_USERKEY);

// Example data
$data = [
    ['param' => '1234567890000000', 'kodedokter' => '12345'],
    ['param' => '0000012345678',    'kodedokter' => '67890'],
];

$total = count($data);
echo "Total data: {$total}\n";

// Log file setup
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

    file_put_contents($logFile, $logMsg, FILE_APPEND);

    sleep(5);
}

echo "Done!";
```

---

## Reference
```init(string $consId, string $secret, string $userKey): self```

Initialize the library with your BPJS API credentials.

```getIcareHistory(string $param, string $kodedokter): string|int|null|false```

Fetch healthcare history data from Icare.
- Returns a URL string on success.
- Returns an integer (seconds to wait) if API requests a retry.
- Returns null if no valid response.
- Returns false if an error occurs.

```getCekVerifikasi3(string $referer): array|null|false```

Fetch verification data from Mobile Faskes using a referer URL.

```postApprovalIC(string $referer): string|null|false```

Submit an approval request to Mobile Faskes using a referer URL.

```wsDecrypt(string $string): string|null```

Decrypts AES-256-CBC encoded and LZString-compressed API data.

---

## License
MIT License © 2025

---

## Disclaimer
**This library is not affiliated with BPJS Kesehatan.
You must be an authorized user with valid credentials to use the BPJS Kesehatan API, and you are responsible for complying with all applicable regulations.**
