<?php namespace Gmopx\FastPaypal\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PayPal\Api\Amount;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\Transaction;
use PayPal\Converter\FormatConverter;
use PayPal\Api\PaymentExecution;
use PayPal\Exception\PayPalConnectionException;


class PayPalController extends Controller
{

    use PaypalTrait;

    /**
     *
     * Una vez que el usuario es redireccionado desde la ventana de pago de Paypal,
     * si el usuario regresa con el parámetro 'success' definido como 'true' finalizamos
     * la transacción ejecutando el pago y posteriormente redireccionamos al usuario
     * a una ruta local de pago completado.
     *
     * Si por el contrario el usuario regresa con el parámetro 'success' definido como 'false'
     * redireccionamos al usuario a una ruta local de pago cancelado por default.
     *
     * @param Request $request
     * @return String
     */
    public function executePayment(Request $request)
    {

        $this->initPaypal();

        if ($request->get('status') === 'success') {

            $currency = $request->get('currency');
            $total = $request->get('total');
            $item = $request->get('item');
            $paymentId = $request->get('paymentId');
            $payment = Payment::get($paymentId, $this->apiContext);

            $execution = new PaymentExecution();
            $execution->setPayerId($request->get('PayerID'));

            $payer = new Payer();
            $payer->setPaymentMethod("paypal");
            $itemListArray = [];

            $item_tmp = new Item();

            $price = FormatConverter::formatToNumber($total);
            $item_tmp->setName($item['name']);
            $item_tmp->setPrice($item['unit_price']);
            $item_tmp->setQuantity($item['quantity']);
            $item_tmp->setCurrency($item['currency']);

            $subtotal = $price;

            $itemListArray[] = $item_tmp;

            $total = $subtotal;

            $itemList = new ItemList();
            $itemList->setItems($itemListArray);

            $amount = new Amount();
            $amount->setCurrency($currency)
                ->setTotal($total);

            $transaction = new Transaction();
            $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription($item['name'])
                ->setInvoiceNumber(uniqid());

            try {
                $result = $payment->execute($execution, $this->apiContext);

                $this->Log($result, true);
                $params = http_build_query(['status' => 'success', 'payment_id' => $payment->getId()]);

                if (($url_success = config('paypal.routes.payment_success')) !== null) {
                    return redirect(route($url_success) . '?' . $params);
                }

                return redirect(route('paypal.status') . '?' . $params);

                //}catch(\Exception $ex){
            } catch (PayPalConnectionException $ex) {

                $this->Log("=================================== PayPalConnectionException ===================================");
                $this->Log($ex);

                //dd('PayPalConnectionException', $ex);

                $params = http_build_query(['payment_id' => $payment->getId(), 'error' => $ex->getMessage(), 'error_url' => $ex->getUrl()]);

                $url_fail = config('paypal.routes.payment_fail', 'paypal.status');

                return redirect(route($url_fail) . '?' . $params);

            } catch (\Exception $ex) {

                $this->Log("=================================== PayPal Exception ===================================");
                $this->Log($ex);

                //dd('Exception', $ex);

                $params = http_build_query(['payment_id' => $payment->getId(), 'error' => $ex->getMessage()]);

                $url_fail = config('paypal.routes.payment_fail', 'paypal.status');

                return redirect(route($url_fail) . '?' . $params);

            }
        }

        return redirect(route('paypal.status') . '?status=cancelled');

    }

    public function cancelPayment(Request $request)
    {
        $html = "<h1>Transacción cancelada / Canceled transaction</h1>";
        $html .= "<h2>No se realizó ningún cargo / No charges were made</h2>";

        return response($html);
    }

    public function paypalDone(Request $request)
    {
        return response()->json($request->all());
    }

    public function paymentError(Request $request)
    {

        $data = $request->all();

        $html = '<h1>Create Paypal payment Error:</h1>';
        $html .= '<code>' . print_r(json_decode($data['error'], true)) . '</code><br/><br/>';
        $html .= '<a href="' . $data['error_url'] . '">Error URL</a>';

        return $html;

    }

    private function Log($data, $recursivo = false)
    {
        Log::info('');
        Log::info(!$recursivo ? $data : print_r($data, true));
        Log::info('');
    }

}
