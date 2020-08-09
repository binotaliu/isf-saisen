<?php

namespace App\Http\Controllers;

use App\Enums\DonationStatus;
use App\Models\Donation;
use App\Models\Payment;
use App\Enums\DonationType;
use App\Services\Ecpay;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class DonationsController extends Controller
{
    public function index()
    {
        return Donation
            ::query()
            ->withCount(['payments'])
            ->with([
                'payments' => function (HasMany $query) {
                    $query->orderByDesc('id');
                },
            ])
            ->whereNull('archive_at')
            ->get();
    }

    /**
     * @throws \Exception
     */
    public function store(Request $request): array
    {
        $this->validate($request, [
            'profile.name' => 'required',
            'profile.phone' => 'required',
            'profile.email' => 'required|email',
            'profile.address' => 'required',
            'payment.type' => 'required|in:' . implode(',', DonationType::values()),
            'payment.amount' => 'required|integer',
            'payment.custom_amount' => 'required_if:amount,0',
        ]);

        $profile = $request->input('profile');
        $payment = $request->input('payment');

        $donation = new Donation;
        $donation->status = DonationStatus::CREATED();
        $donation->uuid = Uuid::uuid4()->toString();
        $donation->name = $profile['name'] ?? '';
        $donation->phone = $profile['phone'] ?? '';
        $donation->email = $profile['email'] ?? '';
        $donation->address = $profile['address'] ?? '';
        $donation->type = new DonationType($payment['type'] ?? 'monthly');
        $donation->count = 99;
        $donation->amount = $payment['amount'] ?: $payment['custom_amount'];
        $donation->message = $payment['message'] ?? '';
        $donation->save();

        return [
            'redirect' => url('donations/' . $donation->uuid . '/checkout'),
        ];
    }

    public function checkout(string $uuid, Ecpay $ecpay)
    {
        /** @var \App\Models\Donation|null $donation */
        $donation = Donation
            ::query()
            ->where('uuid', $uuid)
            ->first();

        abort_if($donation === null, 404);

        // create first payment
        $payment = Payment::createFromDonation($donation);

        $fields = $ecpay->createFrom($payment);

        return view('auto-form', [
            'fields' => $fields,
            'method' => 'POST',
            'action' => $ecpay->getFullUrl('/Cashier/AioCheckOut/V5'),
        ]);
    }

    public function archive(int $id): void
    {
        /** @var \App\Models\Donation $donation */
        $donation = Donation::query()->findOrFail($id);

        $donation->archive_at = Carbon::now();
        $donation->save();
    }
}
