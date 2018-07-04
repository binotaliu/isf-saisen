<?php

namespace App\Http\Controllers\Donations;

use App\Eloquent\Donation;
use App\Eloquent\Payment;
use App\Enums\PaymentStatus;
use App\Services\Ecpay;
use Illuminate\Http\Request as HTTPRequest;
use App\Http\Controllers\Controller;

class CallbackController extends Controller
{
    public function periodCallback(string $uuid, HTTPRequest $reqs, Ecpay $ecpay): string
    {
        /** @var \App\Eloquent\Donation $donation */
        $donation = Donation::query()->where('uuid', $uuid)->firstOrFail();

        // check mac value
        $checkMacValue = $ecpay->generateCheckSum($reqs->all());
        if ($checkMacValue !== $reqs->input('CheckMacValue')) {
            abort(500, 'Invalid CheckMacValue');
        }

        // create new payment
        $payment = Payment::createFromDonation($donation);
        $payment->status = $reqs->input('status') === 1 ? PaymentStatus::PAID() : PaymentStatus::FAILED();
        $payment->save();

        return '1|OK';
    }
}
