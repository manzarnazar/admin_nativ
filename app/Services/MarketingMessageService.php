<?php

namespace App\Services;

use App\Enums\MarketingMessageAudience;
use App\Enums\MarketingMessageStatus;
use App\Enums\PartnerVerificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendMarketingMessageJob;
use App\Models\City;
use App\Models\MarketingMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class MarketingMessageService
{
    /**
     * Create and immediately dispatch a marketing message.
     */
    public function send(array $data, User $admin): MarketingMessage
    {
        $message = MarketingMessage::query()->create([
            'country_id' => $admin->current_country_id,
            'type' => $data['type'],
            'title' => $data['title'],
            'body' => $data['body'],
            'redirect_url' => $data['redirect_url'] ?? null,
            'image' => $data['image'] ?? null,
            'audience' => $data['audience'],
            'city_id' => ($data['audience'] === MarketingMessageAudience::CityBased->value)
                ? ($data['city_id'] ?? null)
                : null,
            'status' => MarketingMessageStatus::Processing,
            'scheduled_at' => now(),
            'created_by' => $admin->id,
        ]);

        SendMarketingMessageJob::dispatch($message);

        return $message;
    }

    /**
     * Create a scheduled marketing message (dispatched later by the scheduler).
     */
    public function schedule(array $data, User $admin): MarketingMessage
    {
        return MarketingMessage::query()->create([
            'country_id' => $admin->current_country_id,
            'type' => $data['type'],
            'title' => $data['title'],
            'body' => $data['body'],
            'redirect_url' => $data['redirect_url'] ?? null,
            'image' => $data['image'] ?? null,
            'audience' => $data['audience'],
            'city_id' => ($data['audience'] === MarketingMessageAudience::CityBased->value)
                ? ($data['city_id'] ?? null)
                : null,
            'status' => MarketingMessageStatus::Scheduled,
            'scheduled_at' => $this->scheduledAtToUtc($data['scheduled_at'], $data['timezone']),
            'scheduled_timezone' => $data['timezone'],
            'created_by' => $admin->id,
        ]);
    }

    /**
     * Reinterpret an admin-entered wall-clock timestamp as being in $timezone, then
     * convert it to the app's UTC instant so ProcessScheduledMarketingMessages's
     * now() comparison is always correct regardless of which zone the admin picked.
     */
    private function scheduledAtToUtc(string $wallClock, string $timezone): Carbon
    {
        return Carbon::parse($wallClock, $timezone)->setTimezone(config('app.timezone'));
    }

    /**
     * Recalculate open_rate from open_count / sent_to for a given message.
     */
    public function recalculateOpenRate(MarketingMessage $message): void
    {
        if (! $message->sent_to || $message->sent_to === 0) {
            return;
        }

        $openRate = round(($message->open_count / $message->sent_to) * 100, 2);
        $message->update(['open_rate' => $openRate, 'clicks' => $message->click_count]);
    }

    /**
     * Resolve the recipients query for a given audience configuration. Used both by
     * SendMarketingMessageJob (persisted MarketingMessage attributes) and by the live
     * "Estimated recipients" count in MarketingNotificationsManage's create form (unsaved
     * form state) — the single source of truth for "who gets a message".
     */
    public function recipientsQuery(MarketingMessageAudience $audience, ?int $cityId): Builder
    {
        return match ($audience) {
            MarketingMessageAudience::All => $this->customersQuery(),
            MarketingMessageAudience::CityBased => $this->cityBasedCustomersQuery($cityId),
            MarketingMessageAudience::Partners => $this->partnersQuery(),
            MarketingMessageAudience::AllUsers => $this->allUsersQuery(),
        };
    }

    private function customersQuery(): Builder
    {
        return User::query()
            ->where('role', UserRole::Customer)
            ->where('status', UserStatus::Active);
    }

    private function cityBasedCustomersQuery(?int $cityId): Builder
    {
        $base = $this->customersQuery();

        if ($cityId) {
            $refCityId = City::query()->where('id', $cityId)->value('ref_city_id');

            if ($refCityId) {
                $base->whereHas('bookings', fn (Builder $b) => $b->whereHas(
                    'property',
                    fn (Builder $p) => $p->where('ref_city_id', $refCityId)
                ));
            }
        }

        return $base;
    }

    private function partnersQuery(): Builder
    {
        return User::query()
            ->where('role', UserRole::Partner)
            ->where('status', UserStatus::Active)
            ->whereHas('partner', fn (Builder $q) => $q
                ->where('verification_status', PartnerVerificationStatus::Approved)
                ->whereNull('suspended_at'));
    }

    private function allUsersQuery(): Builder
    {
        return User::query()
            ->where('status', UserStatus::Active)
            ->where(function (Builder $q) {
                $q->where('role', UserRole::Customer)
                    ->orWhere(fn (Builder $p) => $p->where('role', UserRole::Partner)
                        ->whereHas('partner', fn (Builder $pq) => $pq
                            ->where('verification_status', PartnerVerificationStatus::Approved)
                            ->whereNull('suspended_at')));
            });
    }
}
