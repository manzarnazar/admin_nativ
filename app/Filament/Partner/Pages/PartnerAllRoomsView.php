<?php

namespace App\Filament\Partner\Pages;

use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Partner\Enums\NavigationGroup;
use App\Models\Country;
use App\Models\PropertyRoom;
use App\Models\Review;
use App\Models\Tax;
use App\Models\User;
use App\Support\PartnerContext;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\WithPagination;

class PartnerAllRoomsView extends Page implements DeclaresTopbarControls
{
    use RequiresApprovedPartner;
    use WithPagination;

    protected static ?string $slug = 'all-rooms/{record}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.partner.pages.all-rooms-view';

    public int $propertyRoomId;

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRoom()->roomType?->name ?? __('admin.room_details');
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::RoomManagement;
    }

    public function mount(int $record): void
    {
        /** @var User $user */
        $user = auth()->user();
        $partner = $user->partner;

        $room = PropertyRoom::query()->with('property')->findOrFail($record);

        abort_unless($partner && $room->property?->partner_id === $partner->id, 404);

        $this->propertyRoomId = $room->id;
    }

    public function getRoom(): PropertyRoom
    {
        return PropertyRoom::query()
            ->with([
                'property',
                'roomType.images',
                'roomType.facilities',
            ])
            ->withCount('reviews')
            ->withAvg('reviews as reviews_avg_rating', 'rating')
            ->findOrFail($this->propertyRoomId);
    }

    public function getReviews(): LengthAwarePaginator
    {
        return Review::query()
            ->where('property_room_id', $this->propertyRoomId)
            ->with('user')
            ->latest()
            ->paginate(6);
    }

    public function getCurrencySymbol(): string
    {
        /** @var User $user */
        $user = auth()->user();
        $partner = $user->partner;
        $countryId = $partner ? PartnerContext::currentCountryId($partner) : null;

        return ($countryId ? Country::query()->where('id', $countryId)->value('currency_symbol') : null) ?? '$';
    }

    public function getTaxAmount(): float
    {
        /** @var User $user */
        $user = auth()->user();
        $partner = $user->partner;
        $countryId = $partner ? PartnerContext::currentCountryId($partner) : null;

        if (! $countryId) {
            return 0;
        }

        $room = $this->getRoom();
        $basePrice = (float) $room->base_price_per_night;

        $taxes = Tax::query()
            ->where('country_id', $countryId)
            ->where('status', 'active')
            ->get();

        $totalTax = 0.0;
        foreach ($taxes as $tax) {
            if ($tax->type->value === 'percentage') {
                $totalTax += $basePrice * ((float) $tax->value / 100);
            } else {
                $totalTax += (float) $tax->value;
            }
        }

        return round($totalTax, 2);
    }
}
