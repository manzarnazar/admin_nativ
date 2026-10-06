<?php

namespace App\Filament\Pages;

use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\RoomType;
use App\Support\SystemMode;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class RoomTypeView extends Page implements DeclaresTopbarControls
{
    public static function canAccess(): bool
    {
        return Auth::check();
    }

    protected static ?string $slug = 'room-types/{record}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.room-type-view';

    public RoomType $record;

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function mount(RoomType $record): void
    {
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
        return NavigationGroup::resolve(NavigationGroup::RoomManagement);
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
