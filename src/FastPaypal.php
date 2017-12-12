<?php


namespace Gmopx\FastPaypal;


use Gmopx\FastPaypal\Http\Controllers\PaypalTrait;

class FastPaypal
{
    use PaypalTrait;

    public function start($total, $item)
    {

        $payment = $this->createPayment($total, $item['currency'], $item);

        return $payment;
    }
}