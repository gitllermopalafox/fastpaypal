<?php namespace Gmopx\FastPaypal\Http\Controllers;

use PayPal\Api\Amount;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Converter\FormatConverter;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\PaymentExecution;
use PayPal\Exception\PayPalConnectionException;
use phpDocumentor\Reflection\Types\Mixed_;

trait PaypalTrait
{

    private $apiContext;

    protected function initPaypal()
    {

        // Sandbox
        $clientId = config('paypal.clientId');
        $clientSecret = config('paypal.clientSecret');

        $this->apiContext = new ApiContext(
            new OAuthTokenCredential(
                $clientId,
                $clientSecret
            )
        );

        $this->apiContext->setConfig(
            array(
                'mode' => config('paypal.env'),
                'log.LogEnabled' => true,
                'log.FileName' => storage_path('logs/PayPal.log'),
                'log.LogLevel' => 'DEBUG', // PLEASE USE `FINE` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
                'validation.level' => 'log',
                'cache.enabled' => false,
                // 'http.CURLOPT_CONNECTTIMEOUT' => 30
                // 'http.headers.PayPal-Partner-Attribution-Id' => '123123123'
            )
        );

    }

    /**
     *
     * Crear el objeto de pago de Paypal y lo retornamos.
     *
     * @param $total
     * @param $currency
     * @param $item
     * @return Mixed
     */
    protected function createPayment($total, $currency, $item)
    {

        try {

            $this->initPaypal();

            $payer = new Payer();
            $payer->setPaymentMethod("paypal");
            $itemListArray = [];

            $item_tmp = new Item();

            $total = FormatConverter::formatToNumber(floatval($total));
            $item_tmp->setName($item['name']);
            $item_tmp->setPrice($item['unit_price']);
            $item_tmp->setQuantity($item['quantity']);
            $item_tmp->setCurrency($currency);

            $itemListArray[] = $item_tmp;

            $itemList = new ItemList();
            $itemList->setItems($itemListArray);

            $amount = new Amount();
            $amount->setCurrency($currency)
                ->setTotal($total);

            $transaction = new Transaction();
            $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription("")
                ->setInvoiceNumber(uniqid());

            $url_queries_success = http_build_query([

                'status' => 'success',
                'total' => $total,
                'currency' => $currency,
                'item' => $item

            ]);

            $url_queries_cancel = http_build_query([

                'status' => 'cancel',
                'total' => $total,
                'currency' => $currency,
                'item' => $item

            ]);

            $return_url_success = route('paypal.execute');
            $return_url_cancel = route(config('paypal.routes.payment_cancel', 'paypal.cancel'));

            $redirectUrls = new RedirectUrls();
            $redirectUrls->setReturnUrl($return_url_success . '?' . $url_queries_success)
                ->setCancelUrl($return_url_cancel . '?' . $url_queries_cancel);

            $payment = new Payment();
            $payment->setIntent("sale")
                ->setPayer($payer)
                ->setRedirectUrls($redirectUrls)
                ->setTransactions(array($transaction));

            $payment->create($this->apiContext);

            $obj_return = [
                'status' => 'ok',
                'payment' => $payment
            ];

        } catch (\Exception $ex) {

            //ResultPrinter::printError("Created Payment Using PayPal. Please visit the URL to Approve.", "Payment", null, $request, $ex);
            //exit(1);
            //dd($ex);

            $params = http_build_query(['payment_id' => $payment->getId(), 'error' => $ex->getData(), 'error_url' => $ex->getUrl()]);

            $obj_return['status'] = 'error';
            $obj_return['url'] = route(config('paypal.routes.create_paypal_payment_error', 'paypal.payment_error')) . '?' . $params;;

            return $obj_return;
        }

        return $obj_return;

    }

}
