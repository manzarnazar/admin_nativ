<?php

namespace App\Filament\Contracts;

/**
 * Marker for report pages reached via AllReports (App\Filament\Pages\BookingReport and friends).
 * Implementing it — no methods required — opts a page into the full report-page treatment with
 * zero edits to AdminPanelProvider.php:
 *
 *   - Filters trigger merged into the search/Columns/Exports toolbar row instead of its own row
 *     above (filament.tables.inline-filters-trigger)
 *   - The AboveContentCollapsible filter-fields panel relocated below that toolbar
 *     (filament.tables.inline-filters-panel)
 *   - A "Back to All Reports" link rendered before the page title instead of after
 *     (filament.pages.report-back-link)
 *
 * All three are registered once, unscoped, in AdminPanelProvider — each Blade partial checks
 * `instanceof self` itself rather than relying on Filament's render-hook scopes:, because one of
 * the three (TOOLBAR_AFTER) doesn't pass scopes at its call site and can't be scoped that way at
 * all (see AdminPanelProvider's comment on that hook) — checking the interface everywhere instead
 * keeps the mechanism identical across all three and means a new report page never needs to touch
 * AdminPanelProvider.php.
 *
 * REQUIRED on every implementing page's table(): call ->columnManager(true) even if no column is
 * ->toggleable(). The merged filters trigger lives inside Filament's own toolbar row, and that
 * row's visibility gate (vendor/filament/tables/resources/views/index.blade.php:106-115) never
 * counts AboveContentCollapsible filters as needing a trigger — only $hasColumnManager (reorderable
 * or toggleable columns) does. Without this, a report whose columns are all non-toggleable loses
 * its entire filters UI silently (absent from the rendered HTML, not just unstyled) — found via
 * PartnerWalletReport.php, the first report with zero toggleable columns; every other report was
 * accidentally safe only because it happened to have at least one.
 */
interface IsReportPage {}
