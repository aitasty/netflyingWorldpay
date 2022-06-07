<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-31 13:55:07 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-04 21:48:33
 */

namespace Netflying\WorldpayTest;

use Netflying\Payment\common\Utils;
use Netflying\Payment\common\Request;
use Netflying\PaymentTest\Data;
use Netflying\Payment\common\Openssl;

use Netflying\Worldpay\data\Merchant;

use Netflying\Payment\data\CreditCard;
use Netflying\Payment\data\CreditCardSsl;
use Netflying\Payment\data\RequestCreate;

class Worldpay
{

    protected $url = '';

    public $type = 'Worldpay';

    protected $merchant = [];

    protected $creditCard = [];

    protected $CreditCardSsl = [];

    /**
     * @param $url 回调通知等相对路径
     *
     * @param string $url 站点回调通知相对路径
     */
    public function __construct($url='')
    {
        $this->url = $url;
    }

    /**
     * 商家数据结构
     *
     * @return this
     */
    public function setMerchant(array $realMerchant = [])
    {
        $url = $this->url . '?type=' . $this->type;
        $returnUrl = $url .'&act=return_url&async=0&sn={$sn}';
        $successUrl = $url .'&act=success_url&async=0&sn={$sn}';
        $failureUrl = $url . '&act=failure_url&async=0&sn={$sn}';
        $pendingUrl = $url . '&act=pending_url&async=0&sn={$sn}';
        $cancelUrl = $url . '&act=cancen_url&async=0&sn={$sn}';
        $notifyUrl = $url . '&act=notify_url&async=1&sn={$sn}';
        $threedsUrl = $url .'&act=threeds_url&async=0&sn={$sn}';
        $merchant = [
            'type' => $this->type,
            'is_test' => 1,
            'merchant' => '****',
            'api_account' => [
                'user' => '****',
                'password' => '*****',
                'installation_id' => '*****',
                'encypt_key' => '*****',
            ],
            'api_data' => [
                'endpoint' => 'https://%s:%s@secure-test.worldpay.com/jsp/merchant/xml/paymentService.jsp',
                'return_url' => $returnUrl,
                
                'success_url' => $successUrl,
                'failure_url' => $failureUrl,
                'pending_url' => $pendingUrl,
                'cancel_url' => $cancelUrl,
                'notify_url' => $notifyUrl,
                '3ds_url'    => $threedsUrl,
                'api_3ds' => [
                    'iss' => '****',
                    'org_unit_id' => '******',
                    'jwt_mac_key' => '******',
                    'collect_url' => 'https://centinelapistag.cardinalcommerce.com/V1/Cruise/Collect',
                    'challege_url' => 'https://centinelapistag.cardinalcommerce.com/V2/Cruise/StepUp'
                ],
            ]
        ];
        $merchant = Utils::arrayMerge($merchant,$realMerchant);
        $this->merchant = $merchant;
        return $this;
    }
    public function setCreditCard()
    {
        $this->creditCard = new CreditCard([
            'card_number'    => '4000000000000002',
            'expiry_month'   => '12',
            'expiry_year'    => '2025',
            'cvc'            => '123',
            'holder_name'    => 'join jack',
            'reference' => [
                'threeds_id' => 'asdfasdfasdfasdf',
                'encrypt_data' => 'adsfasdfasdfasdfadsf',
            ]
        ]);
        return $this;
    }
    public function setCreditCardSsl()
    {
        $this->setCreditCard();
        $card = $this->creditCard;
        $this->creditCardSsl = new CreditCardSsl([
            'encrypt' => Openssl::encrypt($card)
        ]);
        return $this;
    }

    /**
     * 提交支付
     *
     * @return Redirect
     */
    public function pay()
    {
        $Data = new Data;
        $Order = $Data->order();
        //设置卡信息
        $this->setCreditCardSsl();
        $Order->setCreditCard($this->creditCardSsl);
        $Log = new Log;
        $Merchant = new Merchant($this->merchant);
        $class = "Netflying\\Worldpay\\lib\\".$this->type;
        $Payment = new $class($Merchant);
        $redirect = $Payment->log($Log)->purchase($Order);
        return $redirect;
    }


}
