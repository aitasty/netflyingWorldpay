<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-31 16:21:26 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-06 15:32:06
 */

namespace Netflying\Worldpay\lib;

use Netflying\Payment\common\Request;
use Netflying\Payment\data\Order;
use Netflying\Payment\data\Redirect;


class Giropay extends Worldpay
{
    public function purchase(Order $Order): Redirect
    {
        $this->merchantUrl($Order);
        $xml = $this->renderXml($Order);
        $apiData = $this->merchant->getApiData();
        $url = $apiData['endpoint'];
        $rs = $this->request($url, $xml);
        $result = $rs['body'];
        if ($rs['code'] != '200') {
        throw new \Exception($rs['code'], $rs['code']);
        }
        $response = new Xml($result);
        //xml错误
        if ($errCode = $response->getErrorCode()) {
            $errMsg  = $response->getErrorDescription();
            throw new \Exception($errMsg, $errCode);
        }
        $url = $response->getRedirectUrl();
        if (!$url) {
        throw new \Exception("Unable to get the redirecting url.");
        }
        return new Redirect([
            'status' => 1,
            'url' => $url,
            'type' => 'get',
            'params' => [],
            'exception' => []
        ]);
    }

    /**
     * xml主体报文
     * @param Order $Order
     * @return string
     */
    protected function renderXml(Order $Order)
    {
        $merchant = $this->merchant;
        $apiData = $merchant['api_data'];
        $merchantCode = $merchant['merchant'];
        $sn = $Order['sn'];
        $orderDescript = $Order['descript'];
        $orderDescript = !empty($orderDescript) ? $orderDescript : Request::domain();
        $desc = Xml::xmlStr($orderDescript); //订单描述,不可为空,可使用站点域名
        $currency = $Order['currency'];
        $amount = $Order['purchase_amount'];
        $address = $Order['address'];
        $shipping = $address['shipping'];
        $email = $shipping['email'];
        $successUrl = Xml::xmlStr($apiData['success_url']);
        $failureUrl = Xml::xmlStr($apiData['failure_url']);
        $cancelUrl = Xml::xmlStr($apiData['cancel_url']);
        $swiftCode = Xml::xmlStr('');
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE paymentService PUBLIC "-//WorldPay//DTD WorldPay PaymentService v1//EN" "http://dtd.worldpay.com/paymentService_v1.dtd">
<paymentService merchantCode="{$merchantCode}" version="1.4">
    <submit>
    <order orderCode="{$sn}">
        <description>{$desc}</description>
        <amount exponent="2" currencyCode="{$currency}" value="{$amount}" />
        <paymentDetails>
        <GIROPAY-SSL>
            <successURL>{$successUrl}</successURL>
            <failureURL>{$failureUrl}</failureURL>
            <cancelURL>{$cancelUrl}</cancelURL>
            <swiftCode>{$swiftCode}</swiftCode>
        </GIROPAY-SSL>
        </paymentDetails>
        <shopper>
        <shopperEmailAddress>{$email}</shopperEmailAddress>
        </shopper>
    </order>
    </submit>
</paymentService>
XML; 
        return $xml;
    }
}