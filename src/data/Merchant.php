<?php
/*
 * @Author: He bin 
 * @Date: 2022-01-26 15:15:22 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-05-25 17:33:52
 */

namespace Netflying\Worldpay\data;

use Netflying\Payment\data\Merchant as MerchantModel;

/**
 * 支付通道基础数据结构
 */
class Merchant extends MerchantModel
{
    protected $apiAccount = [
        'code' => 'string',
        'user' => 'string',
        'password' => 'string',
        'installation_id' => 'string',
    ];
    protected $apiAccountNull = [
        'code' => null,
        'user' => null,
        'password' => null,
        'installation_id' => null,
    ];

}
