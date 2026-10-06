<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Payment;
use App\Support\UserTimezone;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected string $view = 'filament.pages.payment-view';


    public function mount($record): void
    {
        parent::mount($record);

        $user = Filament::auth()->user();
        if ($user && $user->current_country_id) {
            $paymentCountryId = $this->record->booking?->property?->country_id;
            if ($paymentCountryId && $paymentCountryId != $user->current_country_id) {
                Notification::make()
                    ->title(__('admin.payment_not_in_country'))
                    ->warning()
                    ->send();

                $this->redirect(PaymentResource::getUrl('index'));

                return;
            }
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.view_payment');
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getPayment(): Payment
    {
        return $this->record;
    }

    public function getTimezone(): string
    {
        return $this->record->booking?->property?->resolvedTimezone() ?? UserTimezone::current();
    }
}
