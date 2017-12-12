<?php

/*
|--------------------------------------------------------------------------
| Fast Paypal Routes
|--------------------------------------------------------------------------
|
*/

Route::group(['prefix' => 'fast-paypal', 'namespace' => 'App\Packages\FastPaypal\Http\Controllers'], function() {

    Route::get('execute-payment', 'PayPalController@executePayment')->name('paypal.execute');
    Route::get('cancel-payment', 'PayPalController@cancelPayment')->name('paypal.cancel');
    Route::get('payment-status', 'PayPalController@paypalDone')->name('paypal.status');
    Route::get('create-paypal-payment-error', 'PayPalController@paymentError')->name('paypal.payment_error');

});
