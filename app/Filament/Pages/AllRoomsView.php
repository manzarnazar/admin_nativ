<?php

namespace App\Filament\Pages;

use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Country;
use App\Models\PropertyRoom;
use App\Models\Review;
use App\Models\Tax;
use App\Models\User;
use App\Support\SystemMode;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class AllRoomsView extends Page implements DeclaresTopbarControls
{
    use WithPagination;

    public static function canAccess(): bool
    {
        return SystemMode::isSingle() && Auth::check();
    }

    protected static ?string $slug = 'all-rooms/{record}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.all-rooms-view';

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
        return NavigationGroup::resolve(NavigationGroup::RoomManagement);
    }

    public function mount(int $record): void
    {
        $room = PropertyRoom::query()->with('property')->findOrFail($record);

        /** @var User $user */
        $user = auth()->user();

        if ($room->property->country_id !== $user->current_country_id) {
            $this->redirect(AllRoomsManage::getUrl());

            return;
        }

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

        return Country::query()
            ->where('id', $user->current_country_id)
            ->value('currency_symbol') ?? '$';
    }

    public function getTaxAmount(): float
    {
        /** @var User $user */
        $user = auth()->user();
        $room = $this->getRoom();
        $basePrice = (float) $room->base_price_per_night;

        $taxes = Tax::query()
            ->where('country_id', $user->current_country_id)
            ->where('status', 'active')
            ->get();

        $totalTax = 0;
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
