<?php
/**
 * 返回数据基类
 */

namespace Netflying\Worldpay\lib;

/**
 * WP 返回数据模型基类
 *
 * @package WorldPay\Response
 */
abstract class XmlAbstract
{
    /**
     * @var string
     */
    protected $dataString = '';
    /**
     * 解析后的数据
     *
     * @var array
     */
    protected $data = NULl;

    /**
     * Base constructor.
     *
     * @param $string
     */
    public function __construct($string)
    {
        $this->dataString = $string;
    }

    /**
     * 获取数据
     *
     * @return array 数据信息
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 获取数据，根据点号隔开多级
     *
     * @param string $key     KEY值，例如：index1.index2
     * @param mixed  $default 默认值
     * @return array|mixed|null 数据
     */
    final public function get($key, $default = NULL)
    {
        $data = $this->getData();
        $return = NULL;
        if (trim($key)) {
            $return = $data;
            $keys = explode('.', trim($key));
            foreach ($keys as $item) {
                if (isset($return[$item])) {
                    $return = $return[$item];
                } else {
                    $return = $default;
                    break;
                }
            }
        } else {
            $return = $data;
        }

        return $return;
    }

    /**
     * 获取最后事件
     * 
     * @return mixed
     */
    abstract public function getLastEvent();

    /**
     * 获取错误号
     *
     * @return array|mixed|null
     */
    abstract public function getISOReturnCode();

    /**
     * 获取错误描述信息
     *
     * @return mixed
     */
    abstract public function getErrorDescription();

    /**
     * 是否出错，如果有出错就返回错误号，否则返回FALSE
     *
     * @return array|bool|mixed|null
     */
    abstract public function getErrorCode();

    /**
     * 获取支付总额
     * 
     * @return mixed
     */
    abstract public function getPaymentAmount();

    /**
     * 获取支付货币
     * 
     * @return mixed
     */
    abstract public function getPaymentCurrencyCode();

    /**
     * 获取订单CODE
     * 
     * @return mixed
     */
    abstract public function getOrderCode();
}
