<?php

namespace App\Filament\Pages;

use App\Enums\PropertyStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\Property;
use App\Models\User;
use App\Support\SystemMode;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

/**
 * Row = one verification EVENT (approve/correction-request/reject), not one property — there's
 * no dedicated verification-history table, so this reads the activity log entries
 * PropertyVerificationDetail's three actions already write via activity()->event(...). A property
 * with several review cycles shows up as several rows here, oldest to newest.
 *
 * Status column deliberately blends two different fields: for the property's CURRENT state, a
 * Suspended property (properties.status) always shows "Suspended" regardless of what this
 * historical event's own outcome was, since that's the more operationally relevant fact today —
 * otherwise the row shows this event's own verification outcome (Approved/Correction Requested/
 * Rejected, derived directly from the activity's `event` name).
 */
class PropertyVerificationReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/property-verification';

    protected static string $permissionSlug = 'reports';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.report-table-page';

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public static function topbarControls(): array
    {
        return ['country' => true, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.report_property_verification_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_property_verification_desc');
    }

    /**
     * "Submitted" for an approve/reject event (a submission was reviewed to a final outcome),
     * "Re-submit" for a correction-request event (the partner is being asked to submit again).
     * Not a stored field — PropertyVerificationDetail's three actions never record a distinct
     * "documentation status", only the `event` name itself, so this is derived from that.
     */
    private function resolveDocumentation(Activity $record): string
    {
        return $record->event === 'correction_requested'
            ? __('admin.re_submit')
            : __('admin.submitted');
    }

    /**
     * The admin's typed reason (correction/rejection), falling back to the activity's own
     * description ("Property was approved") for events that never collect a reason.
     */
    private function resolveComment(Activity $record): string
    {
        $reason = $record->properties['reason'] ?? null;

        return $reason ?: ($record->description ?? '-');
    }

    private function resolveStatusKey(Activity $record): string
    {
        if ($record->subject?->status === PropertyStatus::Suspended) {
            return 'suspended';
        }

        return $record->event ?? 'approved';
    }

    private function resolveStatusLabel(string $key): string
    {
        return match ($key) {
            'suspended' => __('admin.suspended'),
            'approved' => __('admin.approved'),
            'correction_requested' => __('admin.correction_requested'),
            'rejected' => __('admin.rejected'),
            default => __('admin.approved'),
        };
    }

    private function resolveStatusColor(string $key): string
    {
        return match ($key) {
            'suspended' => 'danger',
            'approved' => 'success',
            'correction_requested' => 'warning',
            'rejected' => 'danger',
            default => 'success',
        };
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $countryId = $user->current_country_id;

        $query = Activity::query()
            ->with(['causer', 'subject.partner.user', 'subject.propertyType'])
            ->where('subject_type', Property::class)
            ->whereIn('event', ['approved', 'correction_requested', 'rejected'])
            // whereHas('subject', ...) is wrong here: `subject` is a MorphTo — Laravel would OR
            // the country_id constraint across every model that ever logs activity (Booking,
            // City, PropertyRule, ...), including ones with no country_id column at all, causing
            // a SQL error. whereHasMorph() scopes the constraint to Property specifically.
            ->whereHasMorph('subject', [Property::class], fn (Builder $q) => $q->where('country_id', $countryId))
            ->latest('created_at');

        return $table
            ->query($query)
            ->searchPlaceholder(__('admin.search_partners'))
            ->columns([
                TextColumn::make('subject_id')
                    ->label(__('admin.property_id'))
                    ->html()
                    ->state(function (Activity $record): Htmlable {
                        if (! $record->subject) {
                            return new HtmlString('-');
                        }

                        return static::linkedNameWithIcon(
                            AllPropertiesView::getUrl(['record' => $record->subject_id]),
                            '#'.str_pad((string) $record->subject_id, 3, '0', STR_PAD_LEFT),
                        );
                    }),

                TextColumn::make('subject.partner.user.name')
                    ->label(__('admin.partner'))
                    ->state(fn (Activity $record): string => $record->subject?->partner?->user?->name ?? '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        // subject is a MorphTo — whereHas('subject.partner.user', ...) can't
                        // traverse through it directly (same class of bug as the country_id
                        // filter above). whereHasMorph() scopes to Property, then a normal
                        // nested whereHas() works fine for the rest of the chain.
                        return $query->whereHasMorph(
                            'subject',
                            [Property::class],
                            fn (Builder $q) => $q->whereHas(
                                'partner.user',
                                fn (Builder $q2) => $q2->where('name', 'like', "%{$search}%")
                            )
                        );
                    }),

                TextColumn::make('subject.propertyType.name')
                    ->label(__('admin.type'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (Activity $record): string => $record->subject?->propertyType?->name ?? '-'),

                TextColumn::make('documentation')
                    ->label(__('admin.documentation'))
                    ->state(fn (Activity $record): string => $this->resolveDocumentation($record))
                    ->toggleable(),

                TextColumn::make('causer.name')
                    ->label(__('admin.verified_by'))
                    ->default('-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('admin.date'))
                    ->date('d M, Y'),

                TextColumn::make('comment')
                    ->label(__('admin.comment'))
                    ->state(fn (Activity $record): string => $this->resolveComment($record))
                    ->limit(30)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->state(fn (Activity $record): string => $this->resolveStatusKey($record))
                    ->formatStateUsing(fn (string $state): string => $this->resolveStatusLabel($state))
                    ->color(fn (string $state): string => $this->resolveStatusColor($state)),
            ])
            ->filters([
                static::dateRangeFilter('created_at'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->deferFilters(false)
            ->columnManager(true)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('property-verification-report')
                    ->exports([
                        'subject_id' => ['label' => 'Property ID', 'formatter' => fn (Activity $r): string => '#'.str_pad((string) $r->subject_id, 3, '0', STR_PAD_LEFT)],
                        'subject.partner.user.name' => 'Partner',
                        'subject.propertyType.name' => 'Type',
                        'documentation' => ['label' => 'Documentation', 'formatter' => fn (Activity $r): string => $this->resolveDocumentation($r)],
                        'causer.name' => 'Verified By',
                        'created_at' => ['label' => 'Date', 'formatter' => fn (Activity $r): string => $r->created_at->format('d M, Y')],
                        'comment' => ['label' => 'Comment', 'formatter' => fn (Activity $r): string => $this->resolveComment($r)],
                        'status' => ['label' => 'Status', 'formatter' => fn (Activity $r): string => $this->resolveStatusLabel($this->resolveStatusKey($r))],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_verification_events_yet'))
            ->emptyStateIcon('heroicon-o-shield-check')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions(static::reportPaginationOptions())
            ->summaries(pageCondition: false, allTableCondition: true);
    }
}
