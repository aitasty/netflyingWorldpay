<?php

namespace Netflying\Worldpay;

use Netflying\Payment\interface\Pay as PayInterface;
use Netflying\Payment\common\Curl;

use Netflying\Worldpay\data\Merchant;
use Netflying\Worldpay\data\Order;
use Netflying\Worldpay\data\OrderProduct;
use Netflying\Worldpay\data\Redirect;
use Netflying\Worldpay\data\OrderPayment;

class Pay implements PayInterface
{
    protected $merchant = null;
    /**
     * 初始化商户
     * @param Merchant $merchant
     * @return void
     */
    public function init(Merchant $merchant)
    {
        $this->merchant = $merchant;
    }
    
    /**
     * 提交支付信息(有的支付需要提交之后payment最后确认并完成)
     * @param Order
     * @return Redirect
     */
    public function purchase(Order $order, OrderProduct $product) {

    }
    /**
     *  统一回调通知接口
     * @return OrderPayment
     */
    public function notify() {

    }

}