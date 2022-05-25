<?php

namespace NetflyingWorldpay;

use Netflying\interface\Pay as PayInterface;
use Netflying\common\Curl;

use NetflyingWorldpay\data\Merchant;
use NetflyingWorldpay\data\Order;
use NetflyingWorldpay\data\OrderProduct;
use NetflyingWorldpay\data\Redirect;
use NetflyingWorldpay\data\OrderPayment;

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