<?php
/**
 * Shygun Web Service PHP 5.6 Client
 * Author: ChatGPT
 * PHP: 5.6 compatible (array() syntax, no scalar type hints)
 *
 * Usage:
 *   $client = new ShygunWebServiceClient('http://81.16.121.68:2030/api', array(
 *      // Choose ONE of the following configuration styles:
 *      // 1) Direct SQL connection info (local WS mode)
 *      'Server'          => 'Web-Service',
 *      'AllowRowSecurity'=> false,
 *      'Level'           => 1,
 *      'DBUserName'      => 'websrv',
 *      'DBPassword'      => '***', // <— replace
 *      'DataBaseName'    => 'cybazg09',
 *      'Language'        => 3,
 *      // Optional auth (when WS is routed via central auth or app auth)
 *      // 'AuthUser'     => '',
 *      // 'AuthPassword' => '',
 *      // OR 2) ConnectionName (if your WS is configured with named connection)
 *      // 'ConnectionName' => 'SampleConnection',
 *   ));
 *
 *   // Example: get account list (with extra fields) for a specific account number
 *   $res = $client->accountGet(array(
 *      'WithExtraFields' => 'true',
 *      'AccountNumber' => array('From' => '212010035', 'To' => '212010035', 'In' => array())
 *   ), 0, 100);
 *   if ($res['ok']) { print_r($res['body']); } else { echo $res['error']; }
 */
class ShygunWebServiceClient
{
    /** @var string */
    protected $baseUrl;
    /** @var array */
    protected $config;
    /** @var int */
    protected $timeout;

    /**
     * @param string $baseUrl e.g. http://host:2030/api
     * @param array $config   Config array as required by WS (Server/DBUserName/DBPassword/DataBaseName/Language/…)
     * @param int $timeoutSec Curl timeout seconds
     */
    public function __construct($baseUrl, array $config, $timeoutSec = 60)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->config  = $config;
        $this->timeout = (int)$timeoutSec;
    }

    /** Update config at runtime */
    public function setConfig(array $config) { $this->config = $config; }

    /** Low-level POST helper */
    protected function post($endpoint, array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = null, $endVersion = null, array $headers = array())
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $qs  = array();
        $qs['RowStart']     = (string)(is_null($rowStart) ? 0 : $rowStart);
        $qs['RowCount']     = (string)(is_null($rowCount) ? 0 : $rowCount);
        if (!is_null($startVersion)) $qs['StartVersion'] = (string)$startVersion;
        if (!is_null($endVersion))   $qs['EndVersion']   = (string)$endVersion;
        if (!empty($qs)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($qs);
        }

        $payload = array(
            'Config' => $this->config,
        );

        // For GET-style endpoints, body can include Start/EndVersion and Domain (per WS spec)
        if (!empty($domain)) {
            $payload['Domain'] = $domain;
        }
        if (!is_null($startVersion) && !isset($payload['StartVersion'])) $payload['StartVersion'] = (string)$startVersion;
        if (!is_null($endVersion)   && !isset($payload['EndVersion']))   $payload['EndVersion']   = (string)$endVersion;

        $json = json_encode($payload);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        $defaultHeaders = array(
            'Content-Type: application/json',
        );
        foreach ($headers as $hk => $hv) {
            if (is_int($hk)) {
                $defaultHeaders[] = $hv; // already a 'Key: Value' string
            } else {
                $defaultHeaders[] = $hk . ': ' . $hv;
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $defaultHeaders);

        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $err   = curl_error($ch);
        $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $requestHeaderString = curl_getinfo($ch, CURLINFO_HEADER_OUT);
        $headerSize = 0;
        $rawHeaders = '';
        $bodyPart   = '';
        $parsedHeaders = array();
        if ($resp !== false) {
            $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $rawHeaders = substr($resp, 0, $headerSize);
            $bodyPart   = substr($resp, $headerSize);
            $parsedHeaders = $this->parseHeaders($rawHeaders);
        }
        curl_close($ch);

        if ($errno) {
            return array(
                'ok' => false,
                'status' => 0,
                'error' => 'CURL error ' . $errno . ': ' . $err,
                'body' => null,
                'rawBody' => $bodyPart,
                'url' => $url,
                'request' => $json,
                'requestHeaders' => $requestHeaderString,
                'responseHeaders' => $parsedHeaders,
                'rawResponseHeaders' => $rawHeaders,
            );
        }
        if ($http < 200 || $http >= 300) {
            return array(
                'ok' => false,
                'status' => $http,
                'error' => 'HTTP ' . $http . ' body: ' . $bodyPart,
                'body' => null,
                'rawBody' => $bodyPart,
                'url' => $url,
                'request' => $json,
                'requestHeaders' => $requestHeaderString,
                'responseHeaders' => $parsedHeaders,
                'rawResponseHeaders' => $rawHeaders,
            );
        }
        $body = json_decode($bodyPart, true);
        if ($body === null && json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'ok' => false,
                'status' => $http,
                'error' => 'Invalid JSON response: ' . json_last_error_msg(),
                'body' => $bodyPart,
                'rawBody' => $bodyPart,
                'url' => $url,
                'request' => $json,
                'requestHeaders' => $requestHeaderString,
                'responseHeaders' => $parsedHeaders,
                'rawResponseHeaders' => $rawHeaders,
            );
        }
        return array(
            'ok' => true,
            'status' => $http,
            'error' => null,
            'body' => $body,
            'rawBody' => $bodyPart,
            'url' => $url,
            'request' => $json,
            'requestHeaders' => $requestHeaderString,
            'responseHeaders' => $parsedHeaders,
            'rawResponseHeaders' => $rawHeaders,
        );
    }

    /** Parse raw header string into associative array */
    protected function parseHeaders($rawHeaders)
    {
        $headers = array();
        if (!is_string($rawHeaders) || $rawHeaders === '') {
            return $headers;
        }
        $blocks = preg_split('/\r\n\r\n|\n\n|\r\r/', trim($rawHeaders));
        $lastBlock = array_pop($blocks);
        if ($lastBlock === null) {
            $lastBlock = '';
        }
        $lines = preg_split('/\r\n|\n|\r/', $lastBlock);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (strpos($line, ':') === false) {
                $headers['Status-Line'] = $line;
                continue;
            }
            $parts = explode(':', $line, 2);
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === '') {
                continue;
            }
            if (!isset($headers[$key])) {
                $headers[$key] = $value;
            } else {
                if (!is_array($headers[$key])) {
                    $headers[$key] = array($headers[$key]);
                }
                $headers[$key][] = $value;
            }
        }
        return $headers;
    }

    /** ========== ABOUT ========== */

    /**
     * About/GetWsInfo — اطلاعات وب‌سرویس.
     *
     * Example:
     * $domain = array();
     * $result = $client->aboutGetWsInfo($domain, 0, 0, '0', '0');
     *
     * @param array $domain Optional Domain payload (معمولاً خالی است)
     * @return array result wrapper
     */
    public function aboutGetWsInfo(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('About/GetWsInfo', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== ACCOUNT ========== */

    /**
     * Account/GetStatement — دریافت صورت‌حساب.
     *
     * Example:
     * $domain = array(
     *     'AccountNumber' => array('From' => '', 'To' => '', 'In' => array()),
     *     'Date'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountGuId'   => array('From' => '', 'To' => '', 'In' => array()),
     *     'JobGuId'       => array('From' => '', 'To' => '', 'In' => array()),
     *     'DocNo'         => array('From' => '', 'To' => '', 'In' => array()),
     *     'Sort'          => array('From' => '0', 'To' => '0', 'In' => array())
     * );
     * $result = $client->accountGetStatement($domain, 0, 0, '0', '');
     */
    public function accountGetStatement(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Account/GetStatement', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Account/Get — دریافت حساب‌ها.
     *
     * Example:
     * $domain = array(
     *     'WithExtraFields' => 'true',
     *     'Sort'            => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountNumber'   => array('From' => '212010035', 'To' => '212010035', 'In' => array()),
     *     'MoinNumber'      => array('From' => '', 'To' => '', 'In' => array()),
     *     'DMoinGuID'       => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountGuId'     => array('From' => '', 'To' => '', 'In' => array()),
     *     'LocationGuId'    => array('From' => '', 'To' => '', 'In' => array()),
     *     'LocationCode'    => array('From' => '', 'To' => '', 'In' => array()),
     *     'SubscriptionCode'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->accountGet($domain, 0, 0, '0', '', true);
     *
     * @param bool $apiVersionHeader Set true to send header api-version: 2.0
     */
    public function accountGet(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = null, $endVersion = null, $apiVersionHeader = false)
    {
        $headers = $apiVersionHeader ? array('api-version' => '2.0') : array();
        return $this->post('Account/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion, $headers);
    }

    /**
     * Account/GetAccountDetail — حساب‌ها با اطلاعات گردش.
     *
     * Example:
     * $domain = array(
     *     'Sort'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountNumber'  => array('From' => '212010035', 'To' => '212010035', 'In' => array()),
     *     'MoinNumber'     => array('From' => '', 'To' => '', 'In' => array()),
     *     'DMoinGuID'      => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountGuID'    => array('From' => '', 'To' => '', 'In' => array()),
     *     'SubscriptionCode'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->accountGetAccountDetail($domain, 0, 0, '0', '', true);
     */
    public function accountGetAccountDetail(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = null, $endVersion = null, $apiVersionHeader = false)
    {
        $headers = $apiVersionHeader ? array('api-version' => '2.0') : array();
        return $this->post('Account/GetAccountDetail', $domain, $rowStart, $rowCount, $startVersion, $endVersion, $headers);
    }

    /**
     * Account/GetCustomerList — دریافت لیست مشتریان حساب.
     *
     * Example:
     * $domain = array(
     *     'WithExtraFields' => 'false',
     *     'Sort'            => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountNumber'   => array('From' => '', 'To' => '', 'In' => array()),
     *     'MoinNumber'      => array('From' => '', 'To' => '', 'In' => array()),
     *     'DMoinGuID'       => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountGuId'     => array('From' => '', 'To' => '', 'In' => array()),
     *     'LocationGuId'    => array('From' => '', 'To' => '', 'In' => array()),
     *     'LocationCode'    => array('From' => '', 'To' => '', 'In' => array()),
     *     'SubscriptionCode'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->accountGetCustomerList($domain, 0, 0, '0', '');
     */
    public function accountGetCustomerList(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Account/GetCustomerList', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== STATEMENT DETAIL ========== */

    /**
     * StatementDetail/Get — جزئیات گردش حساب.
     *
     * Example:
     * $domain = array(
     *     'Sort'         => array('From' => '', 'To' => '', 'In' => array()),
     *     'Date'         => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountGuId'  => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountNumber'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->statementDetailGet($domain, 0, 0, '0', '');
     */
    public function statementDetailGet(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('StatementDetail/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * StatementDetail/GetClosingBalance — مانده حساب.
     *
     * Example:
     * $domain = array(
     *     'Sort'         => array('From' => '', 'To' => '', 'In' => array()),
     *     'Date'         => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountGuId'  => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountNumber'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->statementDetailGetClosingBalance($domain, 0, 0, '0', '');
     */
    public function statementDetailGetClosingBalance(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('StatementDetail/GetClosingBalance', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== BANK TAB ========== */

    /**
     * BankTab/Get — دریافت اطلاعات تب بانک.
     *
     * Example:
     * $domain = array();
     * $result = $client->bankTabGet($domain, 0, 0, '0', '');
     */
    public function bankTabGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('BankTab/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }
    /**
     * BankTab/GetSimple — نسخه ساده دریافت تب بانک.
     *
     * Example:
     * $domain = array();
     * $result = $client->bankTabGetSimple($domain, 0, 0, '0', '');
     */
    public function bankTabGetSimple(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('BankTab/GetSimple', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== JOB (تفصیلی واحد) ========== */

    /**
     * Job/Get — دریافت تفصیلی واحد.
     *
     * Example:
     * $domain = array(
     *     'JobGuId'             => array('From' => '', 'To' => '', 'In' => array()),
     *     'JobGroupGuId'        => array('From' => '', 'To' => '', 'In' => array()),
     *     'JobCostingCode'      => array('From' => '', 'To' => '', 'In' => array()),
     *     'JobCostingGroupNumber'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->jobGet($domain, 0, 0, '0', '');
     */
    public function jobGet(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Job/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Job/GetJobRemainByAccount — مانده تفصیلی واحد بر اساس حساب.
     *
     * Example:
     * $domain = array(
     *     'BaseDate'      => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountNumber' => array('From' => '', 'To' => '', 'In' => array()),
     *     'JobCostingCode'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->jobGetRemainByAccount($domain, 0, 0, '0', '');
     */
    public function jobGetRemainByAccount(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Job/GetJobRemainByAccount', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Job/GetJobStatement — صورت‌حساب تفصیلی واحد.
     *
     * Example:
     * $domain = array(
     *     'CostingCode'  => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountNumber'=> array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountName'  => array('From' => '', 'To' => '', 'In' => array()),
     *     'BaseDate'     => array('From' => '', 'To' => '', 'In' => array()),
     *     'DocNo'        => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->jobGetJobStatement($domain, 0, 0, '0', '');
     */
    public function jobGetJobStatement(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Job/GetJobStatement', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== LOCATION ========== */

    /**
     * Location/Get — دریافت محل‌ها.
     *
     * Example:
     * $domain = array(
     *     'LocationCode' => array('From' => '', 'To' => '', 'In' => array()),
     *     'LocationName' => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->locationGet($domain, 0, 0, '0', '');
     */
    public function locationGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Location/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== ITEM (کالا) ========== */

    /**
     * Item/GetKardex — دریافت کاردکس کالا.
     *
     * Example:
     * $domain = array(
     *     'InvDate'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'InvType'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemMainGroupCode'=> array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemCode'         => array('From' => '', 'To' => '', 'In' => array()),
     *     'STNumber'         => array('From' => '', 'To' => '', 'In' => array()),
     *     'STGuId'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGuId'         => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountGuId'      => array('From' => '', 'To' => '', 'In' => array()),
     *     'JobGuId'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGroupCode'    => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->itemGetKardex($domain, 0, 0, '0', '');
     */
    public function itemGetKardex(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetKardex', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Item/Get — دریافت اطلاعات پایه کالا.
     *
     * Example:
     * $domain = array(
     *     'WithExtraFields' => 'false',
     *     'Sort'            => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemCode'        => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGuId'        => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->itemGet($domain, 0, 0, '0', '');
     */
    public function itemGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Item/GetProductList — دریافت لیست کالا به صورت محصول.
     *
     * Example:
     * $domain = array(
     *     'WithExtraFields' => 'false',
     *     'Sort'            => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemCode'        => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGuId'        => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->itemGetProductList($domain, 0, 0, '0', '');
     */
    public function itemGetProductList(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetProductList', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Item/GetRemain — لیست کالاها به همراه مانده.
     *
     * Example:
     * $domain = array(
     *     'Sort'               => array('From' => '', 'To' => '', 'In' => array()),
     *     'Date'               => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemCode'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGuID'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'StockGuID'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'STNumber'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'ShowItemsZeroRemain'=> array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemMainGroupCode'  => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGroupCode'      => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->itemGetRemain($domain, 0, 0, '0', '');
     */
    public function itemGetRemain(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetRemain', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Item/GetRemainSimple — نسخه ساده مانده کالاها.
     *
     * Example:
     * $domain = array(
     *     'Sort'               => array('From' => '', 'To' => '', 'In' => array()),
     *     'Date'               => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemCode'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGuID'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'StockGuID'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'STNumber'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'ShowItemsZeroRemain'=> array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemMainGroupCode'  => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGroupCode'      => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->itemGetRemainSimple($domain, 0, 0, '0', '');
     */
    public function itemGetRemainSimple(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetRemainSimple', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Item/GetPriceLevel — دریافت سطوح قیمت کالا.
     *
     * Example:
     * $domain = array(
     *     'ItemGuID' => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->itemGetPriceLevel($domain, 0, 0, '0', '');
     */
    public function itemGetPriceLevel(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetPriceLevel', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Item/GetRemainWithBatchNumber — مانده کالا به تفکیک بچ‌نامبر.
     *
     * Example:
     * $domain = array(
     *     'StockGuID' => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGroup' => array('From' => '', 'To' => '', 'In' => array()),
     *     'StartDate' => array('From' => '', 'To' => '', 'In' => array()),
     *     'ToDate'    => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->itemGetRemainWithBatchNumber($domain, 0, 0, '0', '');
     */
    public function itemGetRemainWithBatchNumber(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetRemainWithBatchNumber', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Item/GetRemainWithReserveSimple — مانده کالا با درنظر گرفتن رزرو (نسخه ساده).
     *
     * Example:
     * $domain = array(
     *     'Sort'               => array('From' => '', 'To' => '', 'In' => array()),
     *     'Date'               => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemCode'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGuID'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'StockGuID'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'STNumber'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'ShowItemsZeroRemain'=> array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemMainGroupCode'  => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGroupCode'      => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->itemGetRemainWithReserveSimple($domain, 0, 0, '0', '');
     */
    public function itemGetRemainWithReserveSimple(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetRemainWithReserveSimple', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Item/GetRemainHistoryWithBatchNumber — تاریخچه مانده کالا بر اساس بچ.
     *
     * Example:
     * $domain = array(
     *     'StockGuID' => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGroup' => array('From' => '', 'To' => '', 'In' => array()),
     *     'Date'      => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->itemGetRemainHistoryWithBatchNumber($domain, 0, 0, '0', '');
     */
    public function itemGetRemainHistoryWithBatchNumber(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetRemainHistoryWithBatchNumber', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Item/GetInitialRemain — مانده اول دوره کالا.
     *
     * Example:
     * $domain = array(
     *     'ItemGuID'  => array('From' => '', 'To' => '', 'In' => array()),
     *     'StockGuID' => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->itemGetInitialRemain($domain, 0, 0, '0', '');
     */
    public function itemGetInitialRemain(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetInitialRemain', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Item/GetDefaultPrice — دریافت قیمت پیش‌فرض کالا.
     *
     * Example:
     * $domain = array();
     * $result = $client->itemGetDefaultPrice($domain, 0, 0, '0', '');
     */
    public function itemGetDefaultPrice(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetDefaultPrice', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== SHYGUN ITEM ATTRIBUTE (در برخی استقرارها) ========== */

    /**
     * ShygunItemAttribute/Get — دریافت ویژگی‌های کالا.
     *
     * Example:
     * $domain = array();
     * $result = $client->shygunItemAttributeGet($domain, 0, 0, '0', '');
     */
    public function shygunItemAttributeGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('ShygunItemAttribute/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * ShygunItemAttribute/GetRemain — موجودی کالا بر اساس ویژگی.
     *
     * Example:
     * $domain = array(
     *     'Sort'               => array('From' => '', 'To' => '', 'In' => array()),
     *     'ToDate'             => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemCode'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGuID'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'StockGuID'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'STNumber'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'Attribute'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'ShowItemsZeroRemain'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->shygunItemAttributeGetRemain($domain, 0, 0, '0', '');
     */
    public function shygunItemAttributeGetRemain(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('ShygunItemAttribute/GetRemain', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * ShygunItemAttribute/GetRemainSimple — نسخه ساده مانده بر اساس ویژگی.
     *
     * Example:
     * $domain = array(
     *     'Sort'               => array('From' => '', 'To' => '', 'In' => array()),
     *     'ToDate'             => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemCode'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGuID'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'StockGuID'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'STNumber'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'Attribute'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'ShowItemsZeroRemain'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->shygunItemAttributeGetRemainSimple($domain, 0, 0, '0', '');
     */
    public function shygunItemAttributeGetRemainSimple(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('ShygunItemAttribute/GetRemainSimple', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * ShygunItemAttribute/GetRemainWithReserveSimple — مانده با رزرو بر اساس ویژگی.
     *
     * Example:
     * $domain = array(
     *     'Sort'               => array('From' => '', 'To' => '', 'In' => array()),
     *     'ToDate'             => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemCode'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'ItemGuID'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'StockGuID'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'STNumber'           => array('From' => '', 'To' => '', 'In' => array()),
     *     'Attribute'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'ShowItemsZeroRemain'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->shygunItemAttributeGetRemainWithReserveSimple($domain, 0, 0, '0', '');
     */
    public function shygunItemAttributeGetRemainWithReserveSimple(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('ShygunItemAttribute/GetRemainWithReserveSimple', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * ShygunItemAttributeCombination/Get — دریافت ترکیب ویژگی کالا.
     *
     * Example:
     * $domain = array();
     * $result = $client->shygunItemAttributeCombinationGet($domain, 0, 0, '0', '');
     */
    public function shygunItemAttributeCombinationGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('ShygunItemAttributeCombination/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * ShygunAttributeGroup/Get — گروه ویژگی‌ها.
     *
     * Example:
     * $domain = array();
     * $result = $client->shygunAttributeGroupGet($domain, 0, 0, '0', '');
     */
    public function shygunAttributeGroupGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('ShygunAttributeGroup/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * ShygunAttribute/Get — ویژگی‌های کالا.
     *
     * Example:
     * $domain = array();
     * $result = $client->shygunAttributeGet($domain, 0, 0, '0', '');
     */
    public function shygunAttributeGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('ShygunAttribute/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== ACCOUNT GROUP (سرگروه) ========== */

    /**
     * Sargorooh/GetList — دریافت لیست سرگروه‌ها.
     *
     * Example:
     * $domain = array(
     *     'Sort'      => array('From' => '', 'To' => '', 'In' => array()),
     *     'Kol1Number'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->sargoroohGetList($domain, 0, 0, '0', '');
     */
    public function sargoroohGetList(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Sargorooh/GetList', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Sargorooh/Get — دریافت اطلاعات سرگروه.
     *
     * Example:
     * $domain = array(
     *     'Sort'      => array('From' => '', 'To' => '', 'In' => array()),
     *     'Kol1Number'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->sargoroohGet($domain, 0, 0, '0', '');
     */
    public function sargoroohGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Sargorooh/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== MOIN ========== */

    /**
     * Moin/GetList — دریافت لیست معین.
     *
     * Example:
     * $domain = array(
     *     'Sort'       => array('From' => '', 'To' => '', 'In' => array()),
     *     'MoinNumber' => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->moinGetList($domain, 0, 0, '0', '');
     */
    public function moinGetList(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Moin/GetList', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Moin/Get — دریافت اطلاعات معین.
     *
     * Example:
     * $domain = array(
     *     'Sort'       => array('From' => '', 'To' => '', 'In' => array()),
     *     'MoinNumber' => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->moinGet($domain, 0, 0, '0', '');
     */
    public function moinGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Moin/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== KOL ========== */

    /**
     * Kol/Get — دریافت اطلاعات کل.
     *
     * Example:
     * $domain = array(
     *     'Sort'       => array('From' => '', 'To' => '', 'In' => array()),
     *     'Kol1Number' => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->kolGet($domain, 0, 0, '0', '');
     */
    public function kolGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Kol/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Kol/GetList — دریافت لیست کل.
     *
     * Example:
     * $domain = array(
     *     'Sort'       => array('From' => '', 'To' => '', 'In' => array()),
     *     'Kol1Number' => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->kolGetList($domain, 0, 0, '0', '');
     */
    public function kolGetList(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Kol/GetList', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== CHEQUE ========== */

    /**
     * Cheque/GetReceivableList — لیست چک‌های دریافتی.
     *
     * Example:
     * $domain = array(
     *     'Sort'       => array('From' => '', 'To' => '', 'In' => array()),
     *     'ChequeNo'   => array('From' => '', 'To' => '', 'In' => array()),
     *     'ChequeDate' => array('From' => '', 'To' => '', 'In' => array()),
     *     'Bank'       => array('From' => '', 'To' => '', 'In' => array()),
     *     'Branch'     => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountNo'  => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->chequeGetReceivableList($domain, 0, 0, '0', '');
     */
    public function chequeGetReceivableList(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Cheque/GetReceivableList', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Cheque/GetPayableList — لیست چک‌های پرداختی.
     *
     * Example:
     * $domain = array(
     *     'Sort'       => array('From' => '', 'To' => '', 'In' => array()),
     *     'ChequeNo'   => array('From' => '', 'To' => '', 'In' => array()),
     *     'ChequeDate' => array('From' => '', 'To' => '', 'In' => array()),
     *     'Bank'       => array('From' => '', 'To' => '', 'In' => array()),
     *     'Branch'     => array('From' => '', 'To' => '', 'In' => array()),
     *     'AccountNo'  => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->chequeGetPayableList($domain, 0, 0, '0', '');
     */
    public function chequeGetPayableList(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Cheque/GetPayableList', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== CITY & DEPARTMENT ========== */

    /**
     * City/Get — دریافت شهرها.
     *
     * Example:
     * $domain = array();
     * $result = $client->cityGet($domain, 0, 0, '0', '');
     */
    public function cityGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('City/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Department/Get — دریافت مراکز هزینه.
     *
     * Example:
     * $domain = array();
     * $result = $client->departmentGet($domain, 0, 0, '0', '');
     */
    public function departmentGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Department/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== DOCUMENT & INVOICE ========== */

    /**
     * Document/GetByVersion — دریافت اسناد بر اساس نسخه.
     *
     * Example:
     * $domain = array(
     *     'AccountGuid' => array('From' => '', 'To' => '', 'In' => array()),
     *     'JobGuid'     => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->documentGetByVersion($domain, 0, 0, '0', '');
     */
    public function documentGetByVersion(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Document/GetByVersion', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Invoice/Get — دریافت فاکتورها.
     *
     * Example:
     * $domain = array(
     *     'Sort'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'InvoiceNumber' => array('From' => '', 'To' => '', 'In' => array()),
     *     'InvoiceType'   => array('From' => '', 'To' => '', 'In' => array()),
     *     'InvoiceDate'   => array('From' => '', 'To' => '', 'In' => array()),
     *     'ControlCheck'  => array('From' => '', 'To' => '', 'In' => array()),
     *     'Printed'       => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->invoiceGet($domain, 0, 0, '0', '');
     */
    public function invoiceGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Invoice/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Invoice/GetComplexInvoice — دریافت فاکتور پیچیده.
     *
     * Example:
     * $domain = array(
     *     'WithExtraFields' => 'false',
     *     'InvType'         => 2,
     *     'AccountGuid'     => '00000000-0000-0000-0000-000000000000',
     *     'JobGuid'         => '00000000-0000-0000-0000-000000000000',
     *     'SotckGuid'       => '00000000-0000-0000-0000-000000000000',
     *     'ControlCheck'    => 0,
     *     'Printed'         => 0,
     *     'InvoiceList'     => array(),
     *     'SourceInvoiceList'=> array()
     * );
     * $result = $client->invoiceGetComplexInvoice($domain, 0, 0, '0', '');
     */
    public function invoiceGetComplexInvoice(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Invoice/GetComplexInvoice', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Invoice/GetByMoreDetails01 — دریافت فاکتور با جزئیات بیشتر.
     *
     * Example:
     * $domain = array(
     *     'Sort'          => array('From' => '', 'To' => '', 'In' => array()),
     *     'InvoiceNumber' => array('From' => '', 'To' => '', 'In' => array()),
     *     'InvoiceType'   => array('From' => '', 'To' => '', 'In' => array()),
     *     'InvoiceDate'   => array('From' => '', 'To' => '', 'In' => array()),
     *     'ControlCheck'  => array('From' => '', 'To' => '', 'In' => array()),
     *     'Printed'       => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->invoiceGetByMoreDetails01($domain, 0, 0, '0', '');
     */
    public function invoiceGetByMoreDetails01(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Invoice/GetByMoreDetails01', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * InvoiceAbsList/GetInvoiceAbsList — خلاصه فاکتورها.
     *
     * Example:
     * $domain = array(
     *     'InvType'    => 0,
     *     'InvTypes'   => array(0),
     *     'AccountGuid'=> '00000000-0000-0000-0000-000000000000',
     *     'JobGuid'    => '00000000-0000-0000-0000-000000000000',
     *     'GeneralRef' => 'string',
     *     'FromDate'   => '14020514',
     *     'ToDate'     => '14020523',
     *     'InvNo'      => 0
     * );
     * $result = $client->invoiceAbsListGetInvoiceAbsList($domain, 0, 0, '0', '');
     */
    public function invoiceAbsListGetInvoiceAbsList(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('InvoiceAbsList/GetInvoiceAbsList', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * InvoicePayment/Get — دریافت پرداخت‌های فاکتور.
     *
     * Example:
     * $domain = array(
     *     'Sort'         => array('From' => '', 'To' => '', 'In' => array()),
     *     'InvNo'        => array('From' => '', 'To' => '', 'In' => array()),
     *     'InvDate'      => array('From' => '', 'To' => '', 'In' => array()),
     *     'ValidPayment' => array('From' => '', 'To' => '', 'In' => array()),
     *     'VouchListNo'  => array('From' => '', 'To' => '', 'In' => array()),
     *     'VouchListDate'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->invoicePaymentGet($domain, 0, 0, '0', '');
     */
    public function invoicePaymentGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('InvoicePayment/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== ITEM GROUP ========== */

    /**
     * ItemGroup/GetList — دریافت گروه‌های کالا.
     *
     * Example:
     * $domain = array(
     *     'Sort'        => array('From' => '', 'To' => '', 'In' => array()),
     *     'SortOnAuxId' => array('From' => '', 'To' => '', 'In' => array()),
     *     'GroupNumber' => array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->itemGroupGetList($domain, 0, 0, '0', '');
     */
    public function itemGroupGetList(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('ItemGroup/GetList', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== PAY TYPE ========== */

    /**
     * PayType/Get — دریافت روش‌های پرداخت.
     *
     * Example:
     * $domain = array();
     * $result = $client->payTypeGet($domain, 0, 0, '0', '');
     */
    public function payTypeGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('PayType/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * PayType/GetSimple — نسخه ساده روش‌های پرداخت.
     *
     * Example:
     * $domain = array();
     * $result = $client->payTypeGetSimple($domain, 0, 0, '0', '');
     */
    public function payTypeGetSimple(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('PayType/GetSimple', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== STOCK ========== */

    /**
     * Stock/Get — دریافت انبارها.
     *
     * Example:
     * $domain = array(
     *     'Sort'    => array('From' => '', 'To' => '', 'In' => array()),
     *     'STNumber'=> array('From' => '', 'To' => '', 'In' => array())
     * );
     * $result = $client->stockGet($domain, 0, 0, '0', '');
     */
    public function stockGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Stock/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== STATE & UNIT ========== */

    /**
     * State/Get — دریافت استان‌ها.
     *
     * Example:
     * $domain = array();
     * $result = $client->stateGet($domain, 0, 0, '0', '');
     */
    public function stateGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('State/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Unit/Get — دریافت واحدها.
     *
     * Example:
     * $domain = array();
     * $result = $client->unitGet($domain, 0, 0, '0', '');
     */
    public function unitGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Unit/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Unit/GetConflictedUnits — واحدهای دارای تداخل.
     *
     * Example:
     * $domain = array();
     * $result = $client->unitGetConflictedUnits($domain, 0, 0, '0', '');
     */
    public function unitGetConflictedUnits(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Unit/GetConflictedUnits', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Unit/FixConflictedUnits — رفع تداخل واحدها.
     *
     * Example:
     * $domain = array();
     * $result = $client->unitFixConflictedUnits($domain, 0, 0, '0', '');
     */
    public function unitFixConflictedUnits(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Unit/FixConflictedUnits', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== SPECIAL ========== */

    /**
     * Special/KardexCorrection — اصلاح کاردکس.
     *
     * Example:
     * $domain = array();
     * $result = $client->specialKardexCorrection($domain, 0, 0, null, null);
     */
    public function specialKardexCorrection(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = null, $endVersion = null)
    {
        return $this->post('Special/KardexCorrection', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }
    /** ========== INVOICE / DOCUMENT PUT (optional skeletons) ========== */
    /**
     * ارسال داده به وب‌سرویس (Put endpoints).
     *
     * Example:
     * $putObjects = array(
     *     array(
     *         'Header' => array('DocNo' => '1001', 'DocDate' => '14020101'),
     *         'Rows'   => array(
     *             array('AccountNumber' => '1010101', 'Debit' => 100000, 'Credit' => 0)
     *         )
     *     )
     * );
     * $result = $client->put('Document/Put', $putObjects);
     */
    public function put($endpoint, array $putObjects)
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $payload = array(
            'Put' => array(
                'Config'    => $this->config,
                'PutObject' => $putObjects
            )
        );
        // Many Shygun servers actually expect the object at root not nested under "Put". If you face errors,
        // switch to the alternative payload shape below:
        // $payload = array('Config' => $this->config, 'PutObject' => $putObjects);

        $json = json_encode($payload);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $err   = curl_error($ch);
        $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno) return array('ok' => false, 'status' => 0, 'error' => 'CURL error '.$errno.': '.$err, 'body' => null, 'url' => $url, 'request' => $json);
        if ($http < 200 || $http >= 300) return array('ok' => false, 'status' => $http, 'error' => 'HTTP '.$http.' body: '.$resp, 'body' => null, 'url' => $url, 'request' => $json);
        $body = json_decode($resp, true);
        if ($body === null && json_last_error() !== JSON_ERROR_NONE) {
            return array('ok' => false, 'status' => $http, 'error' => 'Invalid JSON response: '.json_last_error_msg(), 'body' => $resp, 'url' => $url, 'request' => $json);
        }
        return array('ok' => true, 'status' => $http, 'error' => null, 'body' => $body, 'url' => $url, 'request' => $json);
    }

    /**
     * Document/Put — ثبت سند.
     * Example:
     * $documents = array(array('Header' => array('DocNo' => '1001'), 'Rows' => array()));
     * $result = $client->documentPut($documents);
     */
    public function documentPut(array $documents) { return $this->put('Document/Put', $documents); }

    /**
     * Invoice/Put — ثبت فاکتور.
     * Example:
     * $invoices = array(array('Header' => array('InvNo' => '5001'), 'Items' => array()));
     * $result = $client->invoicePut($invoices);
     */
    public function invoicePut(array $invoices)  { return $this->put('Invoice/Put',   $invoices);  }

    /**
     * Account/Put — ثبت حساب.
     * Example:
     * $accounts = array(array('AccountNumber' => '1010101', 'AccountName' => 'نمونه'));
     * $result = $client->accountPut($accounts);
     */
    public function accountPut(array $accounts)  { return $this->put('Account/Put',   $accounts);  }

    /**
     * Item/Put — ثبت کالا.
     * Example:
     * $items = array(array('ItemCode' => 'P-001', 'ItemName' => 'محصول نمونه'));
     * $result = $client->itemPut($items);
     */
    public function itemPut(array $items)        { return $this->put('Item/Put',      $items);      }
}
