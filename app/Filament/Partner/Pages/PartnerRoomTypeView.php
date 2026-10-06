<?php

namespace App\Filament\Partner\Pages;

use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Partner\Enums\NavigationGroup;
use App\Models\RoomType;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class PartnerRoomTypeView extends Page implements DeclaresTopbarControls
{
    use RequiresApprovedPartner;

    protected static ?string $slug = 'rooms/{record}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.partner.pages.room-type-view';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public RoomType $record;

    public function mount(RoomType $record): void
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless($record->partner_id === $user->partner?->id, 404);

        $this->record = $record->load(['images', 'facilities']);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::RoomManagement;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
