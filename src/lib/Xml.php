<?php

/**
 * 返回数据基类
 */

namespace Netflying\Worldpay\lib;

/**
 * XML模式的模型
 */
class Xml extends XmlAbstract
{
    /**
     * Xml constructor.
     *
     * @param $string
     */
    public function __construct($string)
    {
        parent::__construct($string);
        // 进行解析了
        $this->doParse();
    }

    /**
     * 进行解析
     * 
     * @throws \Exception 没有类型的异常
     * @return void
     */
    protected function doParse()
    {
        $this->data = json_decode(json_encode(simplexml_load_string($this->dataString)), TRUE);
        if ($this->data === FALSE) {
            throw new \Exception("parse xml error:" . implode(";", libxml_get_errors()), 10001);
        }
    }

    public function getLastEvent()
    {
        return $this->get('reply.orderStatus.payment.lastEvent');
    }

    /**
     * 获取ISO错误号
     * 
     * @return array|mixed|null
     */
    public function getISOReturnCode()
    {
        return $this->get('reply.orderStatus.payment.ISO8583ReturnCode.@attributes.code');
    }

    /**
     * 获取ISO错误信息
     * 
     * @return mixed
     */
    public function getISOReturnDescription()
    {
        return $this->get('reply.orderStatus.payment.ISO8583ReturnCode.@attributes.description');
    }

    /**
     * 是否出错，如果有出错就返回错误号，否则返回FALSE
     * 
     * @return array|bool|mixed|null
     */
    public function getErrorCode()
    {
        $errorKeys = array(
            'reply.orderStatus.error.@attributes.code',
            'reply.error.@attributes.code',
        );
        $error = NULL;
        foreach ($errorKeys as $errorKey) {
            $error = $this->get($errorKey);
            if (!is_null($error)) {
                break;
            }
        }

        return $error;
    }

    /**
     * 获取错误描述
     * 
     * @return array|mixed|null
     */
    public function getErrorDescription()
    {
        $errorKeys = array(
            'reply.orderStatus.error.@attributes.value',
            'reply.error.@attributes.value',
        );
        $error = NULL;
        foreach ($errorKeys as $errorKey) {
            $error = $this->get($errorKey);
            if (!is_null($error)) {
                break;
            }
        }

        // 如果没有找到
        if (!$error) {
            //  进行解析下
            $ereg = '/<error code=\"[\d]*\"><\!\[CDATA\[(.*)\]\]><\/error>/';
            preg_match($ereg, $this->dataString, $matches);
            if ($matches) {
                $error = isset($matches[1]) ? $matches[1] : '';
            }
        }

        return $error;
    }

    /**
     * 获取支付总金额
     * 
     * @return float
     */
    public function getPaymentAmount()
    {
        $value = $this->get('reply.orderStatus.payment.amount.@attributes.value');
        $exponent = $this->get('reply.orderStatus.payment.amount.@attributes.exponent');

        $amount = $value / ($exponent ? pow(10, $exponent) : 100);
        if ($this->getPaymentCurrencyCode() == 'JPY') {
            $amount *= 100;
        }

        return $amount;
    }

    /**
     * 获取支付的货币
     * 
     * @return array|mixed|null
     */
    public function getPaymentCurrencyCode()
    {
        return $this->get('reply.orderStatus.payment.amount.@attributes.currencyCode');
    }

    /**
     * 获取订单ID
     * 
     * @return array|mixed|null
     */
    public function getOrderCode()
    {
        return $this->get('reply.orderStatus.orderCode');
    }

    /**
     * 获取风险评分,RiskScore
     * 
     * @return integer|null 分数
     */
    public function getRiskScore()
    {
        return $this->get('reply.orderStatus.payment.riskScore.@attributes.value');
    }
    /**
     * 获取3ds cavv判断是否走3ds模式
     */
    public function getCavv()
    {
        return $this->get('reply.orderStatus.payment.ThreeDSecureResult.cavv');
    }


    /**
     * 3d认证-PaRequest
     * @return array|mixed|null
     */
    public function getPaRequest()
    {
        return $this->get('reply.orderStatus.requestInfo.request3DSecure.paRequest');
    }

    /**
     * 3d认证-IssuerURL
     * @return array|mixed|null
     */
    public function getIssuerURL()
    {
        $error = '';
        $ereg = '/<issuerURL><\!\[CDATA\[(.*)\]\]><\/issuerURL>/';
        preg_match($ereg, $this->dataString, $matches);
        if ($matches) {
            $error = isset($matches[1]) ? $matches[1] : '';
        }
        return $error;
    }

    /**
     * 获取跳转的URL
     * 
     * @return array|mixed|null
     */
    public function getRedirectUrl()
    {
        return $this->get('reply.orderStatus.reference');
    }

    /**
     * 3dv2认证-IssuerURL
     * @return array|mixed|null
     */
    public function getAcsURL()
    {
        $error = '';
        $ereg = '/<acsURL><\!\[CDATA\[(.*)\]\]><\/acsURL>/';
        preg_match($ereg, $this->dataString, $matches);
        if ($matches) {
            $error = isset($matches[1]) ? $matches[1] : '';
        }
        return $error;
    }

    /**
     * 3dv2认证-PaRequest
     * @return array|mixed|null
     */
    public function getPayload()
    {
        return $this->get('reply.orderStatus.challengeRequired.threeDSChallengeDetails.payload');
    }

    /**
     * 3dv2认证-getTransactionId3DS
     * @return array|mixed|null
     */
    public function getTransactionId3DS()
    {
        return $this->get('reply.orderStatus.challengeRequired.threeDSChallengeDetails.transactionId3DS');
    }
    /**
     * 字符型特殊处理
     *
     * @param [type] $val
     * @return void
     */
    public static function xmlStr($val)
    {
        if (is_numeric($val) || !is_string($val)) {
            return $val;
        }
        $val = htmlspecialchars($val);
        return '<![CDATA[' . $val . ']]>';
    }
}
