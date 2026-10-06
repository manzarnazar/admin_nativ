<?php

namespace App\Filament\Pages;

use App\Enums\PropertyVerificationStatus;
use App\Enums\Status;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Property;
use App\Models\PropertyRegistrationValue;
use App\Models\RegistrationField;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class PropertyVerificationManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithTable;

    protected static ?string $slug = 'property-verification';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.property-verification';

    public static function getNavigationLabel(): string
    {
        return __('admin.property_verification');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::PropertyManagement);
    }

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.property_verification');
    }

    public function getSubheading(): ?string
    {
        return __('admin.property_verification_subheading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Property::query()
                    ->with(['partner.user', 'propertyType', 'refCity', 'refState'])
                    ->whereHas('partner')
                    ->whereNotIn('verification_status', [
                        PropertyVerificationStatus::Approved->value,
                    ])
                    ->latest()
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.property_info'))
                    ->weight(FontWeight::Medium)
                    ->description(fn (Property $record): string => collect([$record->refCity?->name, $record->refState?->name])->filter()->implode(', ') ?: '-')
                    ->searchable()
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('propertyType.name')
                    ->label(__('admin.type'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('partner.user.name')
                    ->label(__('admin.owner_name'))
                    ->description(fn (Property $record): string => $record->partner?->user?->email ?? '')
                    ->limit(35),

                TextColumn::make('document_progress')
                    ->label(__('admin.documents'))
                    ->html()
                    ->state(function (Property $record): string {
                        $total = RegistrationField::query()
                            ->applicableTo($record->country_id, $record->property_type_id)
                            ->where('status', Status::Active)
                            ->count();

                        if ($total === 0) {
                            return '<span class="text-sm text-gray-400">—</span>';
                        }

                        $filled = PropertyRegistrationValue::query()
                            ->where('property_id', $record->id)
                            ->count();

                        $filled = min($filled, $total);
                        $percentage = (int) round(($filled / $total) * 100);
                        $color = $percentage >= 100 ? '#22c55e' : ($percentage >= 50 ? '#f59e0b' : '#ef4444');

                        return sprintf(
                            '<div class="flex items-center gap-2">
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">%d/%d</span>
                                <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div class="h-1.5 rounded-full" style="width:%d%%;background-color:%s;"></div>
                                </div>
                            </div>',
                            $filled, $total, $percentage, $color
                        );
                    }),

                TextColumn::make('verification_status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (PropertyVerificationStatus $state): string => $state->label())
                    ->color(fn (PropertyVerificationStatus $state): string => $state->color()),

                TextColumn::make('created_at')
                    ->label(__('admin.submitted_date'))
                    ->date('d M, Y'),
            ])
            ->filters([
                SelectFilter::make('verification_status')
                    ->label(__('admin.status'))
                    ->options(collect(PropertyVerificationStatus::cases())->mapWithKeys(
                        fn (PropertyVerificationStatus $s) => [$s->value => $s->label()]
                    )),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(fn (Property $record): string => in_array($record->verification_status, [
                        PropertyVerificationStatus::Pending,
                        PropertyVerificationStatus::Resubmission,
                    ]) ? __('admin.review') : __('admin.view_details'))
                    ->button()
                    ->color(fn (Property $record): string => in_array($record->verification_status, [
                        PropertyVerificationStatus::Pending,
                        PropertyVerificationStatus::Resubmission,
                    ]) ? 'primary' : 'gray')
                    ->url(fn (Property $record): string => PropertyVerificationDetail::getUrl().'?propertyId='.$record->id),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('property-verification')
                    ->exports([
                        'name' => __('admin.property_name'),
                        'partner.user.name' => __('admin.owner_name'),
                        'partner.user.email' => __('admin.email'),
                        'verification_status' => [
                            'label' => __('admin.status'),
                            'formatter' => fn (Property $record): string => $record->verification_status->label(),
                        ],
                        'created_at' => __('admin.submitted_date'),
                    ])
                    ->toActionGroup(),
            ])
            ->searchPlaceholder(__('admin.search_by_property_name_or_id'))
            ->emptyStateHeading(__('admin.no_properties_found'))
            ->emptyStateDescription('');
    }
}
