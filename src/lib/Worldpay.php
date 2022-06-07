<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-31 16:21:26 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-06 22:03:42
 */

namespace Netflying\Worldpay\lib;

use SimpleXMLElement;

use Netflying\Payment\common\Utils;
use Netflying\Payment\common\Request as Rt;
use Netflying\Payment\data\Response;
use Netflying\Payment\lib\PayInterface;
use Netflying\Payment\lib\Request;
use Netflying\Payment\lib\Jwt;

use Netflying\Payment\data\Merchant;
use Netflying\Payment\data\Order;
use Netflying\Payment\data\OrderProduct;
use Netflying\Payment\data\Redirect;
use Netflying\Payment\data\OrderPayment;
use Netflying\Payment\data\RequestCreate;


class Worldpay implements PayInterface
{
    protected $merchant = null;
    //日志对象
    protected $log = '';
    /**
     * 默认支付方式
     *
     * @var string
     */
    protected $payMethod = 'VISA-SSL'; //default
    /**
     * 可用支付方式
     */
    protected static $payMethods = [
        'VISA-SSL',
        'ECMC-SSL'
    ];
    /**
     * 成功的支付状态字串数组
     *
     * @var array
     */
    protected static $completeArr = array(
        'SETTLED',
        'AUTHORISED',
    );
    protected static $refundArr = array(
        'REFUNDED',
        'REVERSED',
        'RETURNENED',
        'SENT_FOR_REFUND', // 退款的前一个状态，但直接认为是退单
        'CHARGED_BACK',
        'CHARGEBACK',
        'INFORMATION_REQUESTED', // 纠结的状态
    );
    protected static $refundedArr = array( //认定为退款状态
        'SENT_FOR_REFUND',
        'CHARGED_BACK',
    );
    protected static $cancelArr = array(
        'REFUSED',
        'CANCELED',
        'CANCELLED',
        'CANCELED-REVERSAL'
    );
    //是否开启3ds
    protected $en3ds = true;

    public function __construct($merchant, $log = '')
    {
        $this->merchant($merchant);
        $this->log($log);
    }

    /**
     * 初始化商户
     * @param Merchant $merchant
     * @return self
     */
    public function merchant(Merchant $merchant)
    {
        $apiAccount = $merchant['api_account'];
        $apiData = $merchant['api_data'];
        $user = $apiAccount['user'];
        $password = $apiAccount['password'];
        $apiData['endpoint'] = sprintf($apiData['endpoint'], urlencode($user), urlencode($password));
        $merchant->setApiData($apiData);
        $this->merchant = $merchant;
        return $this;
    }
    public function merchantUrl(Order $order)
    {
        $sn = $order['sn'];
        $merchant = $this->merchant;
        $apiData = $merchant['api_data'];
        $sn = $order['sn'];
        $urlReplace = function ($val) use ($sn) {
            return str_replace('{$sn}', $sn, $val);
        };
        $urlData = Utils::modeData([
            'return_url' => '',
            'success_url' => '',
            'failure_url' => '',
            'pending_url' => '',
            'cancel_url' => '',
        ], $apiData, [
            'return_url' => $urlReplace,
            'success_url' => $urlReplace,
            'failure_url' => $urlReplace,
            'pending_url' => $urlReplace,
            'cancel_url' => $urlReplace,
        ]);
        $apiData = array_merge($apiData,$urlData);
        $merchant->setApiData($apiData);
        $this->merchant = $merchant;
        return $this;
    }
    /**
     * 日志对象
     */
    public function log($log = '')
    {
        $this->log = $log;
        return $this;
    }
    public function enable3ds()
    {
        $merchant = $this->merchant;
        $apiData = $merchant['apiData'];
        $api3ds = $apiData['api_3ds'];
        if (!empty($api3ds['iss']) && !empty($jwt3ds['org_unit_id'])) {
            $this->en3ds = true;
        } else {
            $this->en3ds = false;
        }
        return $this;
    }
    public function getEn3ds()
    {
        return $this->en3ds;
    }
    /**
     * 提交支付信息
     * @param Order
     * @param OrderProduct
     * @return Redirect
     */
    public function purchase(Order $Order): Redirect
    {
        $this->merchantUrl($Order);
        $CreditCard = $Order['credit_card']->creditCard();
        $Order->setCreditCardData($CreditCard);
        $xml = $this->renderXml($Order);
        $apiData = $this->merchant->getApiData();
        $api3ds  = $apiData['api_3ds'];
        $url = $apiData['endpoint'];
        $challegeUrl = $api3ds['challege_url'];
        $rs = $this->request($url, $xml);
        $result = $rs['body'];
        $cookie = $rs['reference']['cookie']; //wp cookie
        if ($rs['code'] != '200') {
            throw new \Exception($rs['code'], $rs['code']);
        }
        $reference = $CreditCard->getReference();
        $status = 0;
        $errMsg = '';
        $errCode = 0;
        $url = '';
        $params = [];
        $type = 'get';
        if (!empty($reference['encrypt_data'])) { //站内直付
            $Xml = new Xml($result);
            //xml错误
            if ($Xml->getErrorCode()) {
                $errCode = $Xml->getErrorCode();
                $errMsg  = $Xml->getErrorDescription();
                throw new \Exception($errMsg, $errCode);
            }
            //信用卡错误
            if ($errCode = $Xml->getISOReturnCode()) {
                throw new \Exception($Xml->getISOReturnDescription(), $errCode);
            }
            $id3ds = $Xml->getTransactionId3DS(); //无感3DS = null
            if ($this->en3ds && $id3ds) { //有感提交3ds
                $params = $this->threedsFormData($Xml, $Order, $cookie);
                $type = 'post';
                $url = $challegeUrl;
            } else {
                return $this->purchaseRedirect($rs, $Order);
            }
        } else {
            // 解析返回的xml
            $simpleXml = new SimpleXMLElement($result);
            $returnUrl = "&successURL=" . urlencode($this->successUrl);
            $returnUrl .= "&failureURL=" . urlencode($this->failureUrl);
            $returnUrl .= "&pendingURL=" . urlencode($this->pendingUrl);
            $payMethod = "&preferredPaymentMethod=" . urlencode($this->payMethod);
            if ($simpleXml->reply->error) {
                $errCode = $simpleXml->reply->error;
                throw new \Exception('xml error', $errCode);
            }
            $ref = (string)$simpleXml->reply->orderStatus->reference;
            $url = $ref . (stripos($ref, '?') == FALSE ? '?' : '') . $returnUrl . $payMethod;
        }
        if (empty($url)) {
            $status = 0;
        }
        return new Redirect([
            'status' => $status,
            'url' => $url,
            'type' => $type,
            'params' => $params,
            'exception' => []
        ]);
    }
    /**
     * 从 Merchant['api_data']['3ds_url'] 的指定跳回地址 (用户已完成3ds,成功或失败)
     *
     * @return Redirect
     */
    public function purchase3ds(Order $Order)
    {
        $data = Utils::mapData([
            'MD' => '',
        ], Rt::receive());
        if (empty($data['MD'])) {
            return new Redirect([
                'status' => 0,
                'url' => '',
                'type' => 'get',
                'params' => [],
                'exception' => [
                    'msg' => 'MD undefined'
                ]
            ]);
        }
        $md = urlDecode($data['MD']);
        parse_str($md, $arr);
        $mdData = Utils::mapData([
            'code' => '',
            'sessionId' => '',
            'cookie' => ''
        ], $arr);
        $merchant = $this->merchant;
        $merchantCode = $merchant['merchant'];
        $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE paymentService PUBLIC "-//Worldpay//DTD Worldpay PaymentService v1//EN" "http://dtd.worldpay.com/paymentService_v1.dtd" >
<paymentService version="1.4" merchantCode="{$merchantCode}">  
<submit>
<order orderCode="{$mdData['code']}">
    <info3DSecure>
    <completedAuthentication/>
    </info3DSecure>
    <session id="{$mdData['sessionId']}"/>
</order>
</submit>
</paymentService>
EOT;
        $cookieMachine = isset($mdData['cookie']) ? $mdData['cookie'] : '';
        $apiData = $merchant->getApiData();
        $url = $apiData['endpoint'];
        $rs = $this->request($url, $xml, $cookieMachine);
        return $this->purchaseRedirect($rs, $Order);
    }

    /**
     * 3ds认证支付执行请求
     * @param $orderId
     * @param $PaRes
     * @param $sessionId
     * @param $cookieMachine 头部cookie
     * @return Xml
     * @throws Exception
     */
    public function submit3ds(Order $Order, $PaRes, $sessionId, $cookieMachine)
    {
        $merchant = $this->merchant;
        $merchantCode = $merchant['merchant'];
        $apiData = $merchant['api_data'];
        $sn = $Order['sn'];
        $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE paymentService PUBLIC "-//WorldPay/DTD WorldPay PaymentService v1//EN" "http://dtd.worldpay.com/paymentService_v1.dtd">
<paymentService version="1.4" merchantCode="{$merchantCode}">
    <submit>
    <order orderCode="{$sn}">
        <info3DSecure>
        <paResponse>{$PaRes}</paResponse>
        </info3DSecure>
        <session id="{$sessionId}"/>
    </order>
    </submit>
</paymentService>
EOT;
        $rs = $this->request($apiData['endpoint'], $xml, $cookieMachine);
        return $this->purchaseRedirect($rs, $Order);
    }

    /**
     *  统一回调通知接口
     * @return OrderPayment
     */
    public function notify(): OrderPayment
    {
        $post = Utils::mapData([
            'data' => '',
            'sign' => ''
        ], $_POST);
        //校验
        if (empty($post['sign'])) {
            throw new \Exception('invalid');
        }
        $apiAccount = $this->merchant->getApiAccount();
        $encryptKey = $apiAccount['encypt_key'];
        $sign = $post['sign'];
        unset($post['sign']);
        $signPass = false;
        if ($post) {
            ksort($post);
            $tmp = [];
            foreach ($post as $key => $value) {
                $tmp[] = "$key={$value}";
            }
            if (md5(implode('&', $tmp) . $encryptKey) == $sign) {
                $signPass = true;
            }
        }
        if (!$signPass) {
            throw new \Exception('invalid', 1);
        }
        $data = json_decode($post['data'], true);
        $payment = Utils::mapData([
            'sn' => '',
            'status_descrip' => '',
            'currency' => '',
            'amount' => '',
            'type_method' => '',
            'pay_id' => '',
            'pay_sn' => '',
        ], $data, [
            'sn' => 'OrderCode',
            'status_descrip' => 'PaymentStatus',
            'currency' => 'PaymentCurrency',
            'amount' => 'PaymentAmount',
            'type_method' => 'PaymentMethod',
            'pay_id' => 'PaymentId',
            'pay_sn' => 'PaymentId',
        ]);
        $merchant = $this->merchant;
        $payment['type'] = $merchant['type'];
        $payment['merchant'] = $merchant['merchant'];
        $payment['fee'] = 0;
        $payment['pay_time'] = 0; //通道返回
        $statusStr = strtoupper($payment['status_descrip']);
        $status = -2;
        if (in_array($statusStr, self::$completeArr)) {
            $status = 1;
        } elseif (in_array($statusStr, self::$cancelArr)) {
            $status = 0;
        } elseif (in_array($statusStr, self::$refundArr)) {
            if (in_array($statusStr, self::$refundedArr)) {
                $status = -1;
            } else {
                $status = 0;
            }
        }
        $payment['status'] = $status;
        return new OrderPayment($payment);
    }
    /**
     * 异常处理跳转
     *
     * @param Response $Rs
     * @param Order $Order
     * @return void
     */
    protected function purchaseRedirect(Response $Rs, Order $Order)
    {
        $status = 0;
        try {
            return $this->purchaseResonse($Rs, $Order);
        } catch (\Exception $e) {
        }
        return new Redirect([
            'status' => $status,
            'url' => '',
            'type' => 'get',
            'params' => [],
            'exception' => []
        ]);
    }

    /**
     * 处理提交支付结果信息
     *
     * @param Response $rs
     * @return void
     */
    protected function purchaseResonse(Response $Rs, Order $Order)
    {
        if ($Rs['code'] != '200') {
            throw new \Exception($Rs['code'], $Rs['code']);
        }
        $merchant = $this->merchant;
        $apiData = $merchant['api_data'];
        $result = $Rs['body'];
        $response = new Xml($result);
        //xml错误
        if ($response->getErrorCode()) {
            $errCode = $response->getErrorCode();
            $errMsg  = $response->getErrorDescription();
            throw new \Exception($errMsg, $errCode);
        }
        //信用卡错误
        if ($errCode = $response->getISOReturnCode()) {
            throw new \Exception($response->getISOReturnDescription(), $errCode);
        }
        // 最后事件的状态
        $lastEvent = $response->getLastEvent();
        // 获取riskscore
        $riskScore = $response->getRiskScore();
        $riskScore = intval($riskScore);
        // ThreeDSecureResult cavv
        $cavv = $response->getCavv(); //3ds码
        //支付状态
        $status = 0;
        $lastEvent = strtoupper($lastEvent);
        if (in_array($lastEvent, self::$completeArr)) {
            $status = 1; //完成状态，已支付
        }
        // if (in_array($lastEvent, self::$refundArr)) {
        //     $status = -1;
        // }
        // if (in_array($lastEvent, self::$cancelArr)) {
        //     $status = 0;
        // }
        $params = [
            'sn' => $Order['sn'],
            'cavv' => $cavv,
            'risk' => $riskScore
        ];
        $url = $apiData['return_url'];
        return new Redirect([
            'status' => $status,
            'url' => $url,
            'type' => 'get',
            'params' => $params,
            'exception' => []
        ]);
    }

    /**
     * JWT structure
     *
     * @param array $otherPayload submit->renderXml->response
     * [
     *      'ReturnUrl' => ‘https://3ds跳转请求地址',
     *      'Payload' => [
     *          'ACSUrl' => '',
     *          'Payload' => '',
     *          'TransactionId'=>''
     *      ],
     *      "ObjectifyPayload" => true
     * ]
     * @return void
     */
    protected function createDdcJwt($otherPayload = [])
    {
        $merchant = $this->merchant;
        $apiData = $merchant['api_data'];
        $api3ds = $apiData['api_3ds'];
        $nowTime = time();
        $iss = isset($api3ds['iss']) ? $api3ds['iss'] : '';
        $org_unit_id = isset($api3ds['org_unit_id']) ? $api3ds['org_unit_id'] : '';
        $jwt_mac_key = isset($api3ds['jwt_mac_key']) ? $api3ds['jwt_mac_key'] : '';
        //JWT 规定了7个官方字段
        $payload = array(
            "jti" => Jwt::createUuid(), //(JWT ID)：编号
            "iat" => $nowTime, //签发时间
            "exp" => $nowTime + 3600, //过期时间. 默认顾客进入结算页停留1个小时
            "iss" => $iss, //签发人
            //Organisational Unit Id - An identity associated with your account.
            //Once boarded to Cardinal in Secure Test, you can find this in the Test version of the MAI. 
            //You'll need to use the Production credentails for Production.
            "OrgUnitId" => $org_unit_id, //私有字段: 组织单位标识 - 与您的帐户关联的标识
        );
        if ($otherPayload) {
            foreach ($otherPayload as $key => $data)
                $payload[$key] = $data;
        }
        $jwt = Jwt::encode($payload, $jwt_mac_key);
        return $jwt;
    }
    /**
     * 以Form形式跳转提交到3ds_url
     *
     * @param Xml $Xml
     * @param Order $Order
     * @param string $cookie
     * @return void
     */
    protected function threedsFormData(Xml $Xml, Order $Order, $cookie)
    {
        //md
        $sn = $Order['sn'];
        $mdArr = [];
        $mdArr['cookie'] = $cookie;
        $mdArr['code'] = $sn;
        $mdArr['countrycode'] = $Order['currency'];
        $mdArr['payMethod'] = $this->payMethod;
        $mdArr['sessionId'] = $Order['session_id'];
        $md = urlencode(http_build_query($mdArr));
        //jwt
        $merchant = $this->merchant;
        $apiData = $merchant['api_data'];
        $threedsUrl = $apiData['3ds_url'];
        $acsURL = $Xml->getAcsURL();
        $payload = $Xml->getPayload();
        $transactionId3DS = $Xml->getTransactionId3DS();
        $otherPayload = [
            "ReturnUrl" => $threedsUrl,
            "Payload" => [
                'ACSUrl' => $acsURL,
                'Payload' => $payload,
                'TransactionId' => $transactionId3DS,
            ],
            "ObjectifyPayload" => true
        ];
        $jwt = $this->createDdcJwt($otherPayload);
        return [
            'MD' => $md,
            'JWT' => $jwt
        ];
    }

    /**
     * xml主体报文
     * @param Order $order
     * @return string
     */
    protected function renderXml(Order $Order)
    {
        $merchant = $this->merchant;
        $apiAccount = $merchant['api_account'];
        $merchantCode = $merchant['merchant'];
        $sn = $Order['sn'];
        $installationId = $apiAccount['installation_id'];
        $orderDescript = $Order['descript'];
        $orderDescript = !empty($orderDescript) ? $orderDescript : Rt::domain();
        $desc = Xml::xmlStr($orderDescript); //订单描述,不可为空,可使用站点域名
        $currency = $Order['currency'];
        $amount = $Order['purchase_amount'];
        $orderContent = Xml::xmlStr('');
        $threedsXml = '';
        if ($this->getEn3ds()) {
            $payments = $this->creditCardXml($Order);
            $threedsXml = $this->threedsXml($Order);
        } else {
            $payments = $this->paymentXml($Order);
        }
        $browserXml = $this->browserXml($Order);
        $shippingXml = $this->shippingXml($Order);
        $address = $Order['address'];
        $shipping = $address['shipping'];
        $email = $shipping['email'];
        $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE paymentService PUBLIC "-//WorldPay/DTD WorldPay PaymentService v1//EN" "http://dtd.worldpay.com/paymentService_v1.dtd">
<paymentService version="1.4" merchantCode="{$merchantCode}">
    <submit>
        <order orderCode="{$sn}" installationId="{$installationId}">
            <description>{$desc}</description>
            <amount value="{$amount}" currencyCode="{$currency}" exponent="2"/>
            <orderContent>{$orderContent}</orderContent>
            {$payments}
            <shopper>
                <shopperEmailAddress>{$email}</shopperEmailAddress>
                {$browserXml}
            </shopper>
            {$shippingXml}
            {$threedsXml}
        </order>
    </submit>
</paymentService>
EOT;
        return $xml;
    }
    /**
     * 帐单地址xml
     * @param Order $Order
     * @return string
     */
    protected function billingXml(Order $Order)
    {
        $address = $Order['address'];
        $billing = $address['billing']->toArray();
        $xmlStr = function ($val) {
            return Xml::xmlStr($val);
        };
        $data = Utils::modeData($billing, [], [
            'street_address' => $xmlStr,
            'street_address1' => $xmlStr,
            'postal_code' => $xmlStr,
            'city' => $xmlStr
        ]);
        $addressXml = <<<EOT
<cardAddress>
            <address>
                <address1>{$data['street_address']}</address1>
                <address2>{$data['street_address1']}</address2>
                <postalCode>{$data['postal_code']}</postalCode>
                <city>{$data['city']}</city>
                <countryCode>{$data['country_code']}</countryCode>
            </address>
            </cardAddress>
EOT;
        return $addressXml;
    }

    /**
     * 快递地址
     *
     * @param Order $Order
     * @return string
     */
    protected function shippingXml(Order $Order)
    {
        $address = $Order['address'];
        $shipping = $address['shipping']->toArray();
        $xmlStr = function ($val) {
            return Xml::xmlStr($val);
        };
        $data = Utils::modeData($shipping, [], [
            'first_name' => $xmlStr,
            'last_name' => $xmlStr,
            'street_address' => $xmlStr,
            'postal_code' => $xmlStr,
            'city' => $xmlStr
        ]);
        $addressXml = <<<EOT
<shippingAddress>
<address>
    <firstName>{$data['first_name']}</firstName>
    <lastName>{$data['last_name']}</lastName>
    <street>{$data['street_address']}</street>
    <postalCode>{$data['postal_code']}</postalCode>
    <city>{$data['city']}</city>
    <countryCode>{$data['country_code']}</countryCode>
    <telephoneNumber>{$data['phone']}</telephoneNumber>
</address>
</shippingAddress>
EOT;
        return $addressXml;
    }

    /**
     * 信用卡信息,3ds明文
     * @param Order $Order
     * @return string
     */
    protected function creditCardXml(Order $Order)
    {

        $addressXml = $this->billingXml($Order);
        $card = $Order['credit_card_data'];
        $ip = $Order['client_ip'];
        $sessionId = $Order['session_id'];
        $sessionString = $ip && $sessionId ? "<session shopperIPAddress=\"{$ip}\" id=\"{$sessionId}\"/>" : "";
        $holderName = Xml::xmlStr($card['holder_name']);
        $payments = <<<EOT
<paymentDetails>
<CARD-SSL>
<cardNumber>{$card['card_number']}</cardNumber> 
    <expiryDate>
    <date month="{$card['expiry_month']}" year="{$card['expiry_year']}"/>
    </expiryDate>
    <cardHolderName>{$holderName}</cardHolderName> 
    <cvc>{$card['cvc']}</cvc>
    {$addressXml}
    </CARD-SSL>
    {$sessionString}
</paymentDetails>
EOT;
        return $payments;
    }
    /**
     * 常规加密
     *
     * @param Order $Order
     * @return string
     */
    protected function paymentXml(Order $Order)
    {
        $card = $Order['credit_card_data'];
        $reference = $card['reference'];
        $encryptData = $reference['encrypt_data'];
        $billingXml = $this->billingXml($Order);
        $ip = $Order['client_ip'];
        $sessionId = $Order['session_id'];
        $sessionString = $ip && $sessionId ? "<session shopperIPAddress=\"{$ip}\" id=\"{$sessionId}\"/>" : "";
        $payments = <<<EOT
<paymentDetails>
    <CSE-DATA>
    <encryptedData>
        {$encryptData}
    </encryptedData>
    {$billingXml}
    </CSE-DATA>
    {$sessionString}
</paymentDetails>
EOT;
        return $payments;
    }

    /**
     * 设备xml
     *
     * @param Order $order
     * @return string
     */
    protected function browserXml(Order $Order)
    {
        $userAgent = Xml::xmlStr($Order['user_agent']);
        $acceptHeader = 'text/html';
        $userAgent = Xml::xmlStr('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.64 Safari/537.36');
        $browserXml = <<<EOT
<browser>
        <acceptHeader>{$acceptHeader}</acceptHeader>
        <userAgentHeader>{$userAgent}</userAgentHeader>
</browser>
EOT;
        return $browserXml;
    }
    /**
     * 设备3ds xml
     * enable3ds 开启3ds
     * @param Order $Order
     * @return string
     */
    protected function threedsXml(Order $Order)
    {
        $card = $Order['credit_card_data'];
        $reference = $card['reference'];
        $threedsId = $reference['threeds_id'];
        //challengeMandated 更新为 noPreference；根据发卡行实际决策做或者不做challenge
        $threedsXml = <<<EOT
<dynamic3DS overrideAdvice="do3DS"/>
<additional3DSData
dfReferenceId="{$threedsId}"
challengeWindowSize="390x400"
challengePreference="noPreference"/>
EOT;
        return $threedsXml;
    }


    protected function request($url, $xml, $cookie = '')
    {
        $headers[] = "Content-Type: text/xml";
        if ($cookie) {
            $headers[] = 'Cookie:' . $cookie . ';path=/';
        }
        $res = Request::create(new RequestCreate([
            'type' => 'post',
            'url' => $url,
            'headers' => $headers,
            'data' => $xml,
            'log' => $this->log
        ]));
        $code = $res['code'];
        $header = $res['header'];
        $body = $res['body'];
        //取头部cookie机器码
        preg_match('/Set-Cookie:(.*);/iU', $header, $str); //正则匹配
        $cookie = '';
        if ($str && isset($str[0])) {
            $cookie = $str[1];
        }
        $res->setReference([
            'cookie' => $cookie
        ]);
        return $res;
    }
}
