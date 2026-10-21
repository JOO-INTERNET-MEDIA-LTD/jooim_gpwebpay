<?php
if (!defined('_PS_VERSION_')) { exit; }

class GpwebpayClient
{
    const HTTP_TEST = 'https://test.3dsecure.gpwebpay.com/pgw/order.do';
    const HTTP_PROD = 'https://3dsecure.gpwebpay.com/pgw/order.do';
    const WS_TEST = 'https://test.3dsecure.gpwebpay.com/pay-ws/v1/PaymentService';
    const WS_PROD = 'https://3dsecure.gpwebpay.com/pay-ws/v1/PaymentService';

    private $module;
    public function __construct($module) { $this->module = $module; }

    private function getSignatureAlgorithm()
    {
        $hashes = array_map('strtolower', openssl_get_md_methods());
        if (in_array('sha3-512', $hashes)) { return 'sha3-512'; }
        return OPENSSL_ALGO_SHA1;
    }

    public function getHttpEndpoint()
    {
        return (int)Configuration::get('DM_GPWEBPAY_TEST_MODE') ? self::HTTP_TEST : self::HTTP_PROD;
    }

    public function getWsEndpoint()
    {
        return (int)Configuration::get('DM_GPWEBPAY_TEST_MODE') ? self::WS_TEST : self::WS_PROD;
    }

    public function sign(array $values)
    {
        $data = implode('|', array_map('strval', $values));
        $key = $this->getPrivateKey();
        $signature = '';
        if (!openssl_sign($data, $signature, $key, $this->getSignatureAlgorithm())) {
            throw new Exception('GP webpay signature generation failed.');
        }
        if (function_exists('openssl_free_key')) { @openssl_free_key($key); }
        return base64_encode($signature);
    }

    public function verify(array $values, $signature)
    {
        $public = trim((string)Configuration::get('DM_GPWEBPAY_PUBLIC_KEY'));
        if ($public === '') { return false; }
        $key = openssl_get_publickey($public);
        if (!$key) { return false; }
        $data = implode('|', array_map('strval', $values));
        $decodedSignature = base64_decode((string)$signature, true);
        if ($decodedSignature === false) {
            if (function_exists('openssl_free_key')) { @openssl_free_key($key); }
            return false;
        }

        $algorithms = array();
        $preferred = $this->getSignatureAlgorithm();
        $algorithms[] = $preferred;
        $algorithms[] = OPENSSL_ALGO_SHA1;
        $algorithms[] = 'sha1';
        $algorithms = array_unique($algorithms, SORT_REGULAR);

        foreach ($algorithms as $algorithm) {
            $result = @openssl_verify($data, $decodedSignature, $key, $algorithm);
            if ($result === 1) {
                if (function_exists('openssl_free_key')) { @openssl_free_key($key); }
                return true;
            }
        }

        if (function_exists('openssl_free_key')) { @openssl_free_key($key); }
        return false;
    }

    private function getPrivateKey()
    {
        $pem = trim((string)Configuration::get('DM_GPWEBPAY_PRIVATE_KEY'));
        $password = (string)Configuration::get('DM_GPWEBPAY_PRIVATE_KEY_PASSWORD');
        $key = openssl_get_privatekey($pem, $password);
        if (!$key) { throw new Exception('Invalid GP webpay private key or password.'); }
        return $key;
    }

    public function createDigest(array $params, array $order)
    {
        $values = array();
        foreach ($order as $field) {
            if (array_key_exists($field, $params)) { $values[] = (string)$params[$field]; }
        }
        return $this->sign($values);
    }

    public function verifyHttpResponse(array $params, $merchantNumber)
    {
        return $this->verifyHttpResponseDetailed($params, $merchantNumber)['verified'];
    }

    public function verifyHttpResponseDetailed(array $params, $merchantNumber)
    {
        // Both signatures are mandatory in the provider HTTP response contract.
        $invalid = array('verified'=>false, 'digest_ok'=>false, 'digest1_ok'=>false,
            'digest_candidate'=>'', 'digest1_candidate'=>'', 'candidate_labels'=>array());
        foreach ($params as $value) { if (!is_scalar($value)) { return $invalid; } }
        if (!is_string($params['DIGEST'] ?? null) || $params['DIGEST'] === ''
            || !is_string($params['DIGEST1'] ?? null) || $params['DIGEST1'] === '') { return $invalid; }
        $params = $this->normalizeHttpResponseParams($params);
        $candidates = $this->buildHttpResponseSignatureCandidates($params);

        $digestOk = false;
        $digest1Ok = false;
        $matchedDigest = '';
        $matchedDigest1 = '';

        foreach ($candidates as $label => $values) {
            if (!$digestOk && isset($params['DIGEST']) && $params['DIGEST'] !== '' && $this->verify($values, $params['DIGEST'])) {
                $digestOk = true;
                $matchedDigest = $label;
            }

            if (!$digest1Ok && isset($params['DIGEST1']) && $params['DIGEST1'] !== '') {
                $digest1Values = $values;
                $digest1Values[] = (string)$merchantNumber;
                if ($this->verify($digest1Values, $params['DIGEST1'])) {
                    $digest1Ok = true;
                    $matchedDigest1 = $label;
                }
            }

            if ($digestOk && $digest1Ok) {
                break;
            }
        }

        return array(
            'verified' => ($digestOk && $digest1Ok),
            'digest_ok' => $digestOk,
            'digest1_ok' => $digest1Ok,
            'digest_candidate' => $matchedDigest,
            'digest1_candidate' => $matchedDigest1,
            'candidate_labels' => array_keys($candidates),
        );
    }

    private function normalizeHttpResponseParams(array $params)
    {
        foreach (array('DIGEST', 'DIGEST1') as $field) {
            if (isset($params[$field])) {
                $params[$field] = str_replace(' ', '+', (string)$params[$field]);
            }
        }
        return $params;
    }

    private function buildHttpResponseSignatureCandidates(array $params)
    {
        $operation = isset($params['OPERATION']) && $params['OPERATION'] !== '' ? (string)$params['OPERATION'] : 'CREATE_ORDER';

        $standardOrder = array('ORDERNUMBER','MERORDERNUM','MD','PRCODE','SRCODE','RESULTTEXT','USERPARAM1','ADDINFO','TOKEN','EXPIRY','ACSRES','ACCODE','PANPATTERN','DAYTOCAPTURE','TOKENREGSTATUS','ACRC','RRN','PAR','TRACEID');
        $legacyDetailsOrder = array('ORDERNUMBER','MERORDERNUM','MD','PRCODE','SRCODE','RESULTTEXT','DETAILS','USERPARAM1','ADDINFO','TOKEN','EXPIRY','ACSRES','ACCODE','PANPATTERN','DAYTOCAPTURE','TOKENREGSTATUS','ACRC','RRN','PAR','TRACEID');

        $candidates = array(
            'standard_response_order' => $this->collectSignatureValues($operation, $standardOrder, $params),
        );

        if (array_key_exists('DETAILS', $params)) {
            $candidates['legacy_details_response_order'] = $this->collectSignatureValues($operation, $legacyDetailsOrder, $params);
        }

        return $candidates;
    }

    private function collectSignatureValues($operation, array $order, array $params)
    {
        $values = array((string)$operation);
        foreach ($order as $field) {
            if (array_key_exists($field, $params)) {
                $values[] = (string)$params[$field];
            }
        }
        return $values;
    }

    public function wsCall($operation, $requestName, array $fields)
    {
        if (class_exists('SoapClient')) {
            return $this->wsCallSoapClient($operation, $requestName, $fields);
        }
        return $this->wsCallCurl($operation, $requestName, $fields);
    }

    private function wsCallSoapClient($operation, $requestName, array $fields)
    {
        $signature = $this->sign(array_values($fields));
        $fieldsWithSignature = $fields;
        // WSDL defines signature as xsd:base64Binary. SoapClient must receive binary data;
        // it then serializes it as base64 in the SOAP XML. Sending already-base64 text can
        // produce an invalid signature payload for GP webpay WS operations.
        $fieldsWithSignature['signature'] = base64_decode($signature, true);

        $wsdl = dirname(__DIR__).'/lib/gpwebpay_ws/cws_v1.wsdl';
        if (!is_file($wsdl)) {
            return $this->wsCallCurl($operation, $requestName, $fields);
        }

        $requestLog = $fields;
        $requestLog['signature'] = '[binary signature omitted]';

        try {
            $client = new SoapClient($wsdl, array(
                'cache_wsdl' => WSDL_CACHE_NONE,
                'trace' => true,
                'exceptions' => true,
                'location' => $this->getWsEndpoint(),
                'connection_timeout' => 45,
            ));
            $responseObject = $client->__soapCall($operation, array(array($requestName => $fieldsWithSignature)));
            $responseArray = $this->objectToArray($responseObject);
            $responseName = $this->getWsResponseName($requestName);
            $payload = isset($responseArray[$responseName]) && is_array($responseArray[$responseName]) ? $responseArray[$responseName] : $responseArray;

            return array(
                'http_code' => 200,
                'transport' => 'soapclient',
                'request' => json_encode(array($requestName => $requestLog), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'response' => json_encode($responseArray, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'parsed' => $this->parseWsArray($payload),
            );
        } catch (SoapFault $e) {
            $detailArray = isset($e->detail) ? $this->objectToArray($e->detail) : array();
            $lastResponse = '';
            $lastRequest = '';
            if (isset($client) && is_object($client)) {
                $lastResponse = (string)@$client->__getLastResponse();
                $lastRequest = (string)@$client->__getLastRequest();
            }

            // GP webpay often returns a generic faultstring "Other problem" and puts the
            // useful PRCODE/SRCODE into the SOAP detail. SoapClient does not always expose
            // that detail reliably, so parse the raw last SOAP response as well.
            $parsed = $this->parseWsArray($detailArray);
            if ($lastResponse !== '') {
                $parsedFromXml = $this->parseWsResponse($lastResponse);
                $parsed = array_merge($parsedFromXml, $parsed);
            }

            if (!isset($parsed['faultstring']) || $parsed['faultstring'] === '') {
                $parsed['faultstring'] = $e->getMessage();
            }
            if (!isset($parsed['faultcode']) || $parsed['faultcode'] === '') {
                $parsed['faultcode'] = isset($e->faultcode) ? (string)$e->faultcode : 'SOAP-FAULT';
            }

            $responseForLog = array(
                'faultcode'=>isset($e->faultcode)?$e->faultcode:null,
                'faultstring'=>$e->getMessage(),
                'detail'=>$detailArray,
            );
            if ($lastResponse !== '') { $responseForLog['last_response'] = $lastResponse; }

            return array(
                'http_code' => 500,
                'transport' => 'soapclient',
                'request' => $lastRequest !== '' ? $this->maskSoapSignature($lastRequest) : json_encode(array($requestName => $requestLog), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'response' => json_encode($responseForLog, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'parsed' => $parsed,
            );
        } catch (Exception $e) {
            return array(
                'http_code' => 0,
                'transport' => 'soapclient',
                'request' => json_encode(array($requestName => $requestLog), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'response' => json_encode(array('exception'=>$e->getMessage()), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'parsed' => array('faultstring'=>$e->getMessage(), 'faultcode'=>'LOCAL-EXCEPTION'),
            );
        }
    }

    private function wsCallCurl($operation, $requestName, array $fields)
    {
        $signature = $this->sign(array_values($fields));
        $fields['signature'] = $signature;
        $body = '';
        foreach ($fields as $k => $v) {
            $safeTag = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$k);
            $body .= '<type:'.$safeTag.'>'.htmlspecialchars((string)$v, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</type:'.$safeTag.'>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v1="http://gpe.cz/pay/pay-ws/proc/v1" xmlns:type="http://gpe.cz/pay/pay-ws/proc/v1/type">'
            .'<soapenv:Header/><soapenv:Body><v1:'.$operation.'><v1:'.$requestName.'>'.$body.'</v1:'.$requestName.'></v1:'.$operation.'></soapenv:Body></soapenv:Envelope>';
        $ch = curl_init($this->getWsEndpoint());
        curl_setopt_array($ch, array(
            CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>array('Content-Type: text/xml; charset=utf-8','SOAPAction: ""'),
            CURLOPT_POSTFIELDS=>$xml,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_TIMEOUT=>45,
        ));
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $response === '') { throw new Exception('GP webpay WS error: '.$error); }
        return array('http_code'=>$code, 'transport'=>'curl', 'request'=>$xml, 'response'=>$response, 'parsed'=>$this->parseWsResponse($response));
    }


    private function maskSoapSignature($xml)
    {
        if (!is_string($xml) || $xml === '') { return ''; }
        return preg_replace('/(<[^>]*signature[^>]*>)(.*?)(<\/[^>]*signature>)/is', '$1[signature omitted]$3', $xml);
    }

    private function getWsResponseName($requestName)
    {
        if ($requestName === 'refundRequest') {
            return 'refundRequestResponse';
        }
        return preg_replace('/Request$/', 'Response', $requestName);
    }

    private function objectToArray($value)
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (is_array($value)) {
            $out = array();
            foreach ($value as $k => $v) {
                $out[$k] = $this->objectToArray($v);
            }
            return $out;
        }
        if (is_string($value) && !preg_match('//u', $value)) {
            return '[base64]'.base64_encode($value);
        }
        return $value;
    }

    private function parseWsArray(array $data)
    {
        $out = array();
        $wanted = array('faultcode','faultstring','messageId','state','status','subStatus','primaryReturnCode','secondaryReturnCode','captureAmount','refundAmount','approveAmount','paymentAmount','signature');
        foreach ($wanted as $name) {
            $found = $this->findWsValueRecursive($data, $name);
            if ($found !== null) {
                if ($name === 'signature' && !is_scalar($found)) { continue; }
                $out[$name] = is_scalar($found) ? (string)$found : json_encode($found, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
        }
        return $out;
    }

    private function findWsValueRecursive($data, $name)
    {
        if (!is_array($data)) { return null; }
        if (array_key_exists($name, $data)) { return $data[$name]; }
        foreach ($data as $value) {
            if (is_array($value)) {
                $found = $this->findWsValueRecursive($value, $name);
                if ($found !== null) { return $found; }
            }
        }
        return null;
    }

    private function parseWsResponse($xml)
    {
        $out = array();
        libxml_use_internal_errors(true);
        $s = simplexml_load_string($xml);
        if (!$s) { return $out; }
        $wanted = array('faultcode','faultstring','messageId','state','status','subStatus','primaryReturnCode','secondaryReturnCode','captureAmount','refundAmount','approveAmount','paymentAmount','signature');
        foreach ($wanted as $name) {
            $nodes = $s->xpath('//*[local-name()="'.$name.'"]');
            if ($nodes && isset($nodes[0])) {
                $out[$name] = (string)$nodes[0];
            }
        }
        return $out;
    }

    public function isWsSuccessful(array $result)
    {
        $parsed = isset($result['parsed']) && is_array($result['parsed']) ? $result['parsed'] : array();
        if (isset($parsed['faultcode']) || isset($parsed['faultstring'])) { return false; }
        if (isset($parsed['primaryReturnCode']) && (string)$parsed['primaryReturnCode'] !== '0') { return false; }
        if (!isset($parsed['state']) || $parsed['state'] === '') { return false; }
        return ((int)$result['http_code'] >= 200 && (int)$result['http_code'] < 300);
    }

    public function getWsErrorMessage(array $result)
    {
        $parsed = isset($result['parsed']) && is_array($result['parsed']) ? $result['parsed'] : array();
        $operation = isset($result['operation']) && $result['operation'] !== '' ? (string)$result['operation'] : '';
        $prefix = $operation !== '' ? '['.$operation.'] ' : '';

        if (isset($parsed['primaryReturnCode']) && $parsed['primaryReturnCode'] !== '') {
            $pr = (string)$parsed['primaryReturnCode'];
            $sr = isset($parsed['secondaryReturnCode']) && $parsed['secondaryReturnCode'] !== '' ? (string)$parsed['secondaryReturnCode'] : '0';
            $msg = $prefix.'GP webpay WS rejected the request: PRCODE='.$pr.', SRCODE='.$sr;
            $meaning = $this->describeReturnCode($pr, $sr);
            if ($meaning !== '') { $msg .= ' - '.$meaning; }
            if (isset($parsed['faultstring']) && $parsed['faultstring'] !== '' && $parsed['faultstring'] !== 'Other problem') {
                $msg .= ' ('.$parsed['faultstring'].')';
            }
            return $msg;
        }

        if (isset($parsed['faultstring']) && $parsed['faultstring'] !== '') { return $prefix.$parsed['faultstring']; }
        if (isset($result['http_code']) && (int)$result['http_code'] >= 400) { return $prefix.'GP webpay WS HTTP error '.$result['http_code']; }
        return $prefix.'GP webpay WS did not return a successful payment-management response.';
    }

    private function describeReturnCode($primaryReturnCode, $secondaryReturnCode)
    {
        $primary = array(
            '3' => 'Incorrect content of field',
            '7' => 'Wrong WS request structure',
            '11' => 'Unknown merchant / invalid provider + merchant combination',
            '14' => 'Duplicate order number',
            '15' => 'Object not found',
            '18' => 'Refund amount exceeds captured amount',
            '20' => 'Object not in valid state for this operation',
            '25' => 'Operation not allowed for user',
            '31' => 'Wrong signature',
            '80' => 'Duplicate messageId',
            '1000' => 'Technical problem',
        );
        $secondary = array(
            '6' => 'AMOUNT',
            '11' => 'CREDITNUMBER / capture/refund number',
            '22' => 'ORDER',
            '34' => 'DIGEST / signature',
        );
        $parts = array();
        if (isset($primary[(string)$primaryReturnCode])) { $parts[] = $primary[(string)$primaryReturnCode]; }
        if (isset($secondary[(string)$secondaryReturnCode])) { $parts[] = 'Field: '.$secondary[(string)$secondaryReturnCode]; }
        return implode('; ', $parts);
    }

    public function getPaymentStatus($paymentNumber)
    {
        $messageId = date('YmdHis').mt_rand(100000,999999);
        return $this->wsCall('getPaymentStatus','paymentStatusRequest', array(
            'messageId'=>$messageId,
            'provider'=>(string)Configuration::get('DM_GPWEBPAY_PROVIDER'),
            'merchantNumber'=>(string)Configuration::get('DM_GPWEBPAY_MERCHANT_NUMBER'),
            'paymentNumber'=>(string)$paymentNumber,
        ));
    }

    public function getPaymentDetail($paymentNumber)
    {
        $messageId = date('YmdHis').mt_rand(100000,999999);
        return $this->wsCall('getPaymentDetail','paymentDetailRequest', array(
            'messageId'=>$messageId,
            'provider'=>(string)Configuration::get('DM_GPWEBPAY_PROVIDER'),
            'merchantNumber'=>(string)Configuration::get('DM_GPWEBPAY_MERCHANT_NUMBER'),
            'paymentNumber'=>(string)$paymentNumber,
        ));
    }

    public function processCapture($paymentNumber, $amountMinor)
    {
        $messageId = date('YmdHis').mt_rand(100000,999999);
        return $this->wsCall('processCapture','captureRequest', array(
            'messageId'=>$messageId,
            'provider'=>(string)Configuration::get('DM_GPWEBPAY_PROVIDER'),
            'merchantNumber'=>(string)Configuration::get('DM_GPWEBPAY_MERCHANT_NUMBER'),
            'paymentNumber'=>(string)$paymentNumber,
            'amount'=>(string)$amountMinor,
        ));
    }

    public function processCaptureReverse($paymentNumber, $captureNumber)
    {
        $messageId = date('YmdHis').mt_rand(100000,999999);
        return $this->wsCall('processCaptureReverse','captureReverseRequest', array(
            'messageId'=>$messageId,
            'provider'=>(string)Configuration::get('DM_GPWEBPAY_PROVIDER'),
            'merchantNumber'=>(string)Configuration::get('DM_GPWEBPAY_MERCHANT_NUMBER'),
            'paymentNumber'=>(string)$paymentNumber,
            'captureNumber'=>(string)$captureNumber,
        ));
    }

    public function processAuthorizationReverse($paymentNumber)
    {
        $messageId = date('YmdHis').mt_rand(100000,999999);
        return $this->wsCall('processAuthorizationReverse','authorizationReverseRequest', array(
            'messageId'=>$messageId,
            'provider'=>(string)Configuration::get('DM_GPWEBPAY_PROVIDER'),
            'merchantNumber'=>(string)Configuration::get('DM_GPWEBPAY_MERCHANT_NUMBER'),
            'paymentNumber'=>(string)$paymentNumber,
        ));
    }

    public function refund($paymentNumber, $amountMinor)
    {
        $messageId = date('YmdHis').mt_rand(100000,999999);
        return $this->wsCall('processRefund','refundRequest', array(
            'messageId'=>$messageId,
            'provider'=>(string)Configuration::get('DM_GPWEBPAY_PROVIDER'),
            'merchantNumber'=>(string)Configuration::get('DM_GPWEBPAY_MERCHANT_NUMBER'),
            'paymentNumber'=>(string)$paymentNumber,
            'amount'=>(string)$amountMinor,
        ));
    }

    public function refundSmart($paymentNumber, $refundAmountMinor, $originalAmountMinor)
    {
        $detail = $this->getPaymentDetail($paymentNumber);
        $detailParsed = isset($detail['parsed']) && is_array($detail['parsed']) ? $detail['parsed'] : array();

        if (!$this->isWsSuccessful($detail)) {
            $detail['operation'] = 'getPaymentDetail';
            return $detail;
        }

        $state = isset($detailParsed['state']) ? (int)$detailParsed['state'] : 0;
        $status = isset($detailParsed['status']) ? (string)$detailParsed['status'] : '';
        $captureAmount = isset($detailParsed['captureAmount']) && $detailParsed['captureAmount'] !== '' ? (int)$detailParsed['captureAmount'] : (int)$originalAmountMinor;
        $alreadyRefunded = isset($detailParsed['refundAmount']) && $detailParsed['refundAmount'] !== '' ? (int)$detailParsed['refundAmount'] : 0;
        $availableToRefund = max(0, $captureAmount - $alreadyRefunded);

        if ($refundAmountMinor <= 0 || $refundAmountMinor > $availableToRefund) {
            return array(
                'http_code' => 0,
                'request' => '',
                'response' => '',
                'parsed' => array('faultstring' => 'Invalid refund amount for current GP webpay payment detail. Available amount: '.$availableToRefund),
                'operation' => 'localValidation',
                'detail' => $detail,
            );
        }

        // State 7 means the capture is still in an open batch. GP webpay expects capture reverse first,
        // then a new capture with the remaining amount for partial refunds.
        if ($state === 7 && in_array($status, array('CAPTURED','PARTIAL_PAYMENT'), true)) {
            $reverseResult = null;
            $reverseAccepted = false;
            $lastReverseResult = null;
            for ($captureNumber = 1; $captureNumber <= 10; $captureNumber++) {
                $reverseResult = $this->processCaptureReverse($paymentNumber, $captureNumber);
                $lastReverseResult = $reverseResult;
                if ($this->isWsSuccessful($reverseResult)) {
                    $reverseAccepted = true;
                    break;
                }

                $parsed = isset($reverseResult['parsed']) && is_array($reverseResult['parsed']) ? $reverseResult['parsed'] : array();
                $pr = isset($parsed['primaryReturnCode']) ? (string)$parsed['primaryReturnCode'] : '';
                $sr = isset($parsed['secondaryReturnCode']) ? (string)$parsed['secondaryReturnCode'] : '';
                // PRCODE=20/SRCODE=11 is commonly returned for an invalid captureNumber; try the next one.
                if (!($pr === '20' && $sr === '11')) {
                    break;
                }
            }

            if (!$reverseAccepted) {
                $lastReverseResult['operation'] = 'processCaptureReverse';
                $lastReverseResult['detail'] = $detail;
                return $lastReverseResult;
            }

            $remainingAmount = max(0, $captureAmount - $refundAmountMinor - $alreadyRefunded);
            if ($remainingAmount > 0) {
                $captureResult = $this->processCapture($paymentNumber, $remainingAmount);
                $captureResult['operation'] = 'processCaptureAfterReverse';
                $captureResult['detail'] = $detail;
                $captureResult['reverse'] = $reverseResult;
                return $captureResult;
            }

            $authReverseResult = $this->processAuthorizationReverse($paymentNumber);
            $authReverseResult['operation'] = 'processAuthorizationReverseAfterCaptureReverse';
            $authReverseResult['detail'] = $detail;
            $authReverseResult['reverse'] = $reverseResult;
            return $authReverseResult;
        }

        $refundResult = $this->refund($paymentNumber, $refundAmountMinor);
        $refundResult['operation'] = 'processRefund';
        $refundResult['detail'] = $detail;
        return $refundResult;
    }
}
