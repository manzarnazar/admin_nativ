<?php

namespace App\Filament\Pages;

use App\Enums\PartnerVerificationStatus;
use App\Enums\RegistrationFieldScope;
use App\Enums\Status;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Partner;
use App\Models\PartnerRegistrationValue;
use App\Models\RegistrationField;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class PartnerVerificationManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithTable;

    protected static ?string $slug = 'partner-verification';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.partner-verification';

    public static function getNavigationLabel(): string
    {
        return __('admin.partner_verification');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Partners);
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
        return __('admin.partner_verification');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Partner::query()
                    ->with(['user', 'countries'])
                    ->whereHas('user')
                    ->whereNotIn('verification_status', [
                        PartnerVerificationStatus::Approved->value,
                        PartnerVerificationStatus::Suspended->value,
                    ])
                    ->latest()
            )
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('admin.partner'))
                    ->weight(FontWeight::Medium)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                    })
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('user.email')
                    ->label(__('admin.owner_name'))
                    ->description(fn (Partner $record): string => trim(($record->user?->dial_code ?? '').' '.($record->user?->phone ?? '')))
                    ->limit(35),

                TextColumn::make('created_at')
                    ->label(__('admin.applied_on'))
                    ->date('d M, Y'),

                TextColumn::make('document_progress')
                    ->label(__('admin.documents'))
                    ->html()
                    ->state(function (Partner $record): string {
                        $countryIds = $record->countries->pluck('id')->toArray();

                        if (empty($countryIds)) {
                            return '<span class="text-sm text-gray-400">—</span>';
                        }

                        $total = RegistrationField::query()
                            ->forScope(RegistrationFieldScope::Partner)
                            ->whereIn('country_id', $countryIds)
                            ->where('status', Status::Active)
                            ->count();

                        if ($total === 0) {
                            return '<span class="text-sm text-gray-400">—</span>';
                        }

                        $filled = PartnerRegistrationValue::query()
                            ->where('partner_id', $record->id)
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
                    ->formatStateUsing(fn (PartnerVerificationStatus $state): string => $state->label())
                    ->color(fn (PartnerVerificationStatus $state): string => $state->color()),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(fn (Partner $record): string => in_array($record->verification_status, [
                        PartnerVerificationStatus::Pending,
                        PartnerVerificationStatus::Resubmission,
                    ]) ? __('admin.review') : __('admin.view_details'))
                    ->button()
                    ->color(fn (Partner $record): string => in_array($record->verification_status, [
                        PartnerVerificationStatus::Pending,
                        PartnerVerificationStatus::Resubmission,
                    ]) ? 'primary' : 'gray')
                    ->url(fn (Partner $record): string => PartnerVerificationDetail::getUrl().'?partnerId='.$record->id),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('partner-verification')
                    ->exports([
                        'user.name' => __('admin.partner'),
                        'user.email' => __('admin.email'),
                        'user.phone' => __('admin.phone'),
                        'verification_status' => [
                            'label' => __('admin.status'),
                            'formatter' => fn (Partner $record): string => $record->verification_status->label(),
                        ],
                        'created_at' => __('admin.applied_on'),
                    ])
                    ->toActionGroup(),
            ])
            ->searchPlaceholder(__('admin.search_partners'))
            ->emptyStateHeading(__('admin.no_partners_found'))
            ->emptyStateDescription('');
    }
}
