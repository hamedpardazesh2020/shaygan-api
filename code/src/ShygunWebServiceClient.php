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
        curl_close($ch);

        if ($errno) {
            return array('ok' => false, 'status' => 0, 'error' => 'CURL error '.$errno.': '.$err, 'body' => null, 'url' => $url, 'request' => $json);
        }
        if ($http < 200 || $http >= 300) {
            return array('ok' => false, 'status' => $http, 'error' => 'HTTP '.$http.' body: '.$resp, 'body' => null, 'url' => $url, 'request' => $json);
        }
        $body = json_decode($resp, true);
        if ($body === null && json_last_error() !== JSON_ERROR_NONE) {
            return array('ok' => false, 'status' => $http, 'error' => 'Invalid JSON response: '.json_last_error_msg(), 'body' => $resp, 'url' => $url, 'request' => $json);
        }
        return array('ok' => true, 'status' => $http, 'error' => null, 'body' => $body, 'url' => $url, 'request' => $json);
    }

    /** ========== ABOUT ========== */

    /**
     * About/GetWsInfo
     * @param array $domain Optional domain
     * @return array result wrapper
     */
    public function aboutGetWsInfo(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('About/GetWsInfo', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== ACCOUNT ========== */

    /**
     * Account/GetStatement — دریافت صورت‌حساب
     * Domain keys (DomainStatement): AccountNumber, Date, AccountGuId, JobGuId, DocNo, Sort
     */
    public function accountGetStatement(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Account/GetStatement', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Account/Get — دریافت حساب‌ها
     * Domain keys (DomainAccount): WithExtraFields, Sort, AccountNumber, MoinNumber, DMoinGuID, AccountGuId,
     *                               LocationGuId, LocationCode, SubscriptionCode
     * Some servers require header api-version: 2.0
     */
    public function accountGet(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = null, $endVersion = null, $apiVersionHeader = false)
    {
        $headers = $apiVersionHeader ? array('api-version' => '2.0') : array();
        return $this->post('Account/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion, $headers);
    }

    /**
     * Account/GetAccountDetail — حساب‌ها با اطلاعات گردش
     * Domain keys (DomainAccountByDetail): Sort, AccountNumber, MoinNumber, DMoinGuID, AccountGuID, SubscriptionCode
     */
    public function accountGetAccountDetail(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = null, $endVersion = null, $apiVersionHeader = false)
    {
        $headers = $apiVersionHeader ? array('api-version' => '2.0') : array();
        return $this->post('Account/GetAccountDetail', $domain, $rowStart, $rowCount, $startVersion, $endVersion, $headers);
    }

    /** ========== STATEMENT DETAIL ========== */

    /**
     * StatementDetail/Get — جزئیات گردش حساب (فیلتر بر اساس Date, AccountGuId, AccountNumber, Sort)
     */
    public function statementDetailGet(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('StatementDetail/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * StatementDetail/GetClosingBalance — مانده حساب
     * Domain keys (DomainStatementDetail): Date, AccountGuId, AccountNumber, Sort
     */
    public function statementDetailGetClosingBalance(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('StatementDetail/GetClosingBalance', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== BANK TAB ========== */

    /** BankTab/Get */
    public function bankTabGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('BankTab/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }
    /** BankTab/GetSimple */
    public function bankTabGetSimple(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('BankTab/GetSimple', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== JOB (تفصیلی واحد) ========== */

    /**
     * Job/Get — دریافت تفصیلی واحد
     * Domain keys (DomainJob): JobGuId, JobGroupGuId, JobCostingCode, JobCostingGroupNumber
     */
    public function jobGet(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Job/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Job/GetJobRemainByAccount — مانده تفصیلی واحد بر اساس حساب
     * Domain keys: BaseDate, AccountNumber, JobCostingCode
     */
    public function jobGetRemainByAccount(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Job/GetJobRemainByAccount', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Job/GetJobStatement — مانده صورت‌حساب تفصیلی واحد
     * Domain keys (DomainJobStatement): CostingCode, AccountNumber, AccountName, BaseDate, DocNo
     */
    public function jobGetJobStatement(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Job/GetJobStatement', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== LOCATION ========== */

    /**
     * Location/Get — دریافت محل‌ها
     * Domain keys (LocationDomain): LocationCode, LocationName
     */
    public function locationGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Location/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== ITEM (کالا) ========== */

    /**
     * Item/GetKardex — دریافت کاردکس کالا
     * Domain keys (DomainItemKardex): InvDate, InvType, ItemMainGroupCode, ItemCode, STNumber, STGuId, ItemGuId,
     *                                  AccountGuId, JobGuId, ItemGroupCode
     */
    public function itemGetKardex(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetKardex', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /**
     * Item/GetRemain — لیست کالاها به همراه مانده
     * Domain keys (DomainItemRemain): Sort, Date, ItemCode, ItemGuID, StockGuID, STNumber, ShowItemsZeroRemain,
     *                                 ItemMainGroupCode, ItemGroupCode
     */
    public function itemGetRemain(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetRemain', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** Item/GetRemainSimple — ساده‌شده مانده کالاها */
    public function itemGetRemainSimple(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetRemainSimple', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** Item/GetPriceLevel — دریافت سطوح قیمت برای یک کالا (ItemGuID) */
    public function itemGetPriceLevel(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('Item/GetPriceLevel', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== SHYGUN ITEM ATTRIBUTE (در برخی استقرارها) ========== */

    public function shygunItemAttributeGet(array $domain = array(), $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('ShygunItemAttribute/Get', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }
    public function shygunItemAttributeGetRemain(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('ShygunItemAttribute/GetRemain', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }
    public function shygunItemAttributeGetRemainSimple(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('ShygunItemAttribute/GetRemainSimple', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }
    public function shygunItemAttributeGetRemainWithReserveSimple(array $domain, $rowStart = 0, $rowCount = 0, $startVersion = 0, $endVersion = 0)
    {
        return $this->post('ShygunItemAttribute/GetRemainWithReserveSimple', $domain, $rowStart, $rowCount, $startVersion, $endVersion);
    }

    /** ========== INVOICE / DOCUMENT PUT (optional skeletons) ========== */
    /**
     * Example skeleton for sending data (Put endpoints). Provide full models as needed.
     * $putObjects should be an array of documents/invoices/items shaped per WS spec.
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

    public function documentPut(array $documents) { return $this->put('Document/Put', $documents); }
    public function invoicePut(array $invoices)  { return $this->put('Invoice/Put',   $invoices);  }
    public function accountPut(array $accounts)  { return $this->put('Account/Put',   $accounts);  }
    public function itemPut(array $items)        { return $this->put('Item/Put',      $items);      }
}
