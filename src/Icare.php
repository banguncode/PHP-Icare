<?php

namespace PHPIcare;

use LZCompressor\LZString;

class Icare
{
    private $consId;
    private $secret;
    private $userKey;
    private $apiUri;
    private $mobileFaskesApiUri;
    private $timestamp;
    private $ch;

    public function __construct()
    {
        $this->apiUri = "https://apijkn.bpjs-kesehatan.go.id/wsihs";
        $this->mobileFaskesApiUri = "https://mobile-faskes.bpjs-kesehatan.go.id";

        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($this->ch, CURLOPT_ENCODING, '');
        curl_setopt($this->ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    }

    public function init($consId,  $secret,  $userKey)
    {
        $this->consId = $consId;
        $this->secret = $secret;
        $this->userKey = $userKey;
        return $this;
    }

    /**
     * Get HTTP headers for API requests
     *
     * @return array
     */
    private function getHTTPHeader()
    {
        $tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));
        $this->timestamp = $tStamp;
        $signature = hash_hmac('sha256', $this->consId . "&" . $tStamp, $this->secret, true);
        $encodedSignature = base64_encode($signature);

        return array(
            'X-cons-id: ' . $this->consId,
            'X-timestamp: ' . $tStamp,
            'X-signature: ' . $encodedSignature,
            'user_key: ' . $this->userKey,
            'Accept: application/json',
            'Content-Type: application/json',
            'Cache-Control: no-cache',
            'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        );
    }

    /**
     * Decrypts a string using AES-256-CBC
     *
     * @param string $string
     * @return string|null
     */
    function wsDecrypt($string)
    {
        $key = $this->consId . $this->secret . $this->timestamp;
        $encrypt_method = 'AES-256-CBC';
        $key_hash = hex2bin(hash('sha256', $key));
        $iv = substr(hex2bin(hash('sha256', $key)), 0, 16);
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key_hash, OPENSSL_RAW_DATA, $iv);

        return LZString::decompressFromEncodedURIComponent($output);
    }

    /**
     * Get Icare history
     *
     * @param string $param NIK | No. Peserta BPJS
     * @param string $kodedokter DPJP
     * @return string|null
     */
    function getIcareHistory($param, $kodedokter)
    {
        try {
            $data_string = json_encode(array(
                "param" => (string)preg_replace('/\D/', '', $param),
                "kodedokter" => (int)$kodedokter,
            ));

            curl_setopt($this->ch, CURLOPT_URL, $this->apiUri . "/api/rs/validate");
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHTTPHeader());
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data_string);

            $ws_result = curl_exec($this->ch);

            $data = json_decode($ws_result, true);

            if (isset($data['metaData'])) {
                switch ($data['metaData']['code']) {
                    case 200:
                        $data['response'] = json_decode($this->wsDecrypt($data['response']), true);
                        return $data['response']['url'];
                        break;

                    case 412:
                        $pattern = '/(\d{2}:\d{2}:\d{2})/';
                        preg_match($pattern, $data['metaData']['message'], $matches);
                        if (isset($matches[0])) {
                            $time = explode(':', $matches[0]);
                            $seconds = ($time[0] * 3600 + $time[1] * 60 + $time[2]) + 1;
                            return $seconds;
                        } else {
                            return $data['metaData']['message'] . "\n";
                        }
                        break;

                    default:
                        return $data['metaData']['message'] . "\n";
                        break;
                }
            } else
                return null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get Cek Verifikasi 3
     * @param string $referer
     * @return array|null
     */
    function getCekVerifikasi3($referer)
    {
        try {
            $parsedUrl = parse_url($referer);
            parse_str($parsedUrl['query'], $query);
            $token = $query['token'];

            curl_setopt($this->ch, CURLOPT_URL, $this->mobileFaskesApiUri . "/IHS/cekVerifikasi3");
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
                "Referer: {$referer}",
            ));
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, array(
                "_token" => md5($token),
                "token" => $token,
            ));
            curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 16) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.7204.180 Mobile Safari/537.36');

            $ws_result = curl_exec($this->ch);

            $data = json_decode($ws_result, true);

            if (isset($data['response'])) {
                return $data['response'];
            } else
                return null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get Approval IC
     * @param string $referer
     * @return array|null
     */
    function postApprovalIC($referer)
    {
        try {
            $parsedUrl = parse_url($referer);
            parse_str($parsedUrl['query'], $query);
            $token = $query['token'];

            curl_setopt($this->ch, CURLOPT_URL, $this->mobileFaskesApiUri . "/IHS/approvalIC");
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
                "Referer: {$referer}",
            ));
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, array(
                "_token" => md5($token),
                "token" => $token,
            ));
            curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 16) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.7204.180 Mobile Safari/537.36');

            $ws_result = curl_exec($this->ch);

            $data = json_decode($ws_result, true);

            if (isset($data['response'])) {
                $response = json_decode($data['response'], true);
                return json_encode($response['data']);
            } else
                return null;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }
}
