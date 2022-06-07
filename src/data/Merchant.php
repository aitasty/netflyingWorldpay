<?php
/*
 * @Author: He bin 
 * @Date: 2022-01-26 15:15:22 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-02 15:03:03
 */

namespace Netflying\Worldpay\data;

use Netflying\Payment\data\Merchant as MerchantModel;

/**
 * 支付通道基础数据结构
 */
class Merchant extends MerchantModel
{
    protected $apiAccount = [
        'user' => 'string',
        'password' => 'string',
        'installation_id' => 'string',
        'encypt_key' => 'string',
    ];
    protected $apiAccountNull = [
        'user' => null,
        'password' => null,
        'installation_id' => null,
        'encypt_key' => null, //验证码
    ];
    protected $apiData = [
        /**
         * API请求的URL. 支持使用占位符自动填充
         * live: https://%s:%s@secure.worldpay.com/jsp/merchant/xml/paymentService.jsp
         * sandbox: https://%s:%s@secure-test.worldpay.com/jsp/merchant/xml/paymentService.jsp
         */
        'endpoint' => 'string',
        //完成后最后跳回地址,并带回sn,cavv(3ds码,可选),risk(可选)
        'return_url' => 'string',
        //成功返回地址
        'success_url' => 'string',
        //失败返回地址
        'failure_url' => 'string',
        //处理中地址
        'pending_url' => 'string',
        //取消地址,sofort/giropay需要
        'cancel_url' => 'string',
        //https://路由地址,3ds验证完成跳转并完成最后的支付提交
        '3ds_url' => 'string',
        //flex 3ds api配置参数. 为空不走3ds
        'api_3ds' => 'array',
    ];
    protected $apiDataNull = [
        'endpoint'   => null,
        'return_url' => null,
        'success_url' => null,
        'failure_url' => null,
        'pending_url' => null,
        'cancel_url' => null,
        '3ds_url'    => null,
        'api_3ds' => [], //为空3ds强制不开启
    ];

    protected $api3ds = [
        'iss' => 'string',
        'org_unit_id' => 'string',
        'jwt_mac_key' => 'string',
        //3ds form提交地址
        'challege_url' => 'string',
        //设备sessionId请求收集地址,前端sdk需要
        'collect_url' => 'string',
    ];

    protected $api3dsNull = [
        'iss' => '',
        'org_unit_id' => '',
        'jwt_mac_key' => '',
        'challege_url' => '',
        'collect_url' => '',
    ];
}
