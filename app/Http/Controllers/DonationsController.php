<?php

namespace App\Http\Controllers;

use App\Enums\DonationStatus;
use App\Enums\DonationType;
use App\Models\Donation;
use App\Models\Payment;
use App\Services\Ecpay;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Ramsey\Uuid\Uuid;

class DonationsController extends Controller
{
    public function index(Request $request)
    {
        return view('manage.donations.index', [
            'donations' => Donation
                ::query()
                ->when(
                    DonationType::isValid($request->input('type')),
                    fn ($q) => $q->where('type', $request->input('type'))
                )
                ->when(
                    DonationStatus::isValid($request->input('status')),
                    fn ($q) => $q->where('status', $request->input('status'))
                )
                ->when(
                    $request->input('include_unpaid', false) ||
                    DonationStatus::CREATED()->equals($request->input('status')),
                    fn ($q) => $q,
                    fn ($q) => $q->where('status', '<>', DonationStatus::CREATED())
                )
                ->latest()
                ->with(['latest_payment'])
                ->paginate(20),
        ]);
    }

    public function show(Donation $donation)
    {
        return view('manage.donations.show', [
            'donation' => $donation
        ]);
    }

    /**
     * @throws \Exception
     */
    public function store(Request $request): array
    {
        $this->validate($request, [
            'profile.name' => 'required',
            'profile.phone' => 'required',
            'profile.email' => ['required', 'email'],
            'profile.address' => 'required',
            'payment.type' => ['required', Rule::in(DonationType::values())],
            'payment.amount' => ['required', 'integer', 'in:0,500,1000,2000,3000,4000,5000'],
            'payment.count' => ['required', 'integer', Rule::in(['12', '24', '36', '48', '99'])],
            'payment.custom_amount' => ['required_if:amount,0', 'integer', 'min:100', 'max:10000'],
        ], [], [
            'profile.name' => '姓名',
            'profile.phone' => '電話',
            'profile.email' => 'E-Mail',
            'profile.address' => '地址',
            'payment.type' => '贊助方式',
            'payment.amount' => '贊助金額',
            'payment.count' => '贊助期數',
            'payment.custom_amount' => '贊助金額',
        ]);

        $profile = $request->input('profile');
        $payment = $request->input('payment');

        $donation = new Donation;
        $donation->status = DonationStatus::CREATED();
        $donation->uuid = Uuid::uuid4()->getHex();
        $donation->name = $profile['name'] ?? '';
        $donation->phone = $profile['phone'] ?? '';
        $donation->email = $profile['email'] ?? '';
        $donation->address = $profile['address'] ?? '';
        $donation->type = new DonationType($payment['type'] ?? 'monthly');
        if (DonationType::MONTHLY()->equals($donation->type)) {
            $donation->count =  $payment['count'] ?: 99;
        } else {
            $donation->count =  1;
        }
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
