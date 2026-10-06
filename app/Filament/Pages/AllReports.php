<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Support\SystemMode;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class AllReports extends Page implements DeclaresTopbarControls
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }

    public static function topbarControls(): array
    {
        return ['country' => true, 'property' => false];
    }

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return SystemMode::isMulti();
    }

    protected string $view = 'filament.pages.all-reports';

    public $search = '';

    public string $activeTab = '';

    public function mount()
    {
        $this->activeTab = __('admin.all_reports');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Reports);
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.all_reports');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.all_reports');
    }

    public function getTabsProperty(): array
    {
        return [
            __('admin.all_reports'),
            __('admin.booking_and_customers'),
            __('admin.partner_and_property'),
            __('admin.financial'),
            __('admin.feedback_and_rating'),
            __('admin.system_activity'),
        ];
    }

    public function getReportsDataProperty(): array
    {
        return [
            __('admin.booking_and_customers') => [
                [
                    'title' => __('admin.report_booking_title'),
                    'description' => __('admin.report_booking_desc'),
                    'url' => BookingReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
                [
                    'title' => __('admin.report_cancellation_title'),
                    'description' => __('admin.report_cancellation_desc'),
                    'url' => CancellationReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
                [
                    'title' => __('admin.report_customer_title'),
                    'description' => __('admin.report_customer_desc'),
                    'url' => CustomerReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
                // [
                //     'title' => __('admin.report_customer_wallet_title'),
                //     'description' => __('admin.report_customer_wallet_desc'),
                //     'url' => '#',
                //     'icon' => 'heroicon-o-information-circle',
                // ],
                // ,
                // [
                //     'title' => __('admin.report_refer_earn_title'),
                //     'description' => __('admin.report_refer_earn_desc'),
                //     'url' => '#',
                //     'icon' => 'heroicon-o-information-circle'
                // ]
            ],
            __('admin.partner_and_property') => [
                [
                    'title' => __('admin.report_partner_title'),
                    'description' => __('admin.report_partner_desc'),
                    'url' => PartnerReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
                [
                    'title' => __('admin.report_partner_payout_title'),
                    'description' => __('admin.report_partner_payout_desc'),
                    'url' => PartnerPayoutReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
                [
                    'title' => __('admin.report_partner_wallet_title'),
                    'description' => __('admin.report_partner_wallet_desc'),
                    'url' => PartnerWalletReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
                [
                    'title' => __('admin.report_property_title'),
                    'description' => __('admin.report_property_desc'),
                    'url' => PropertyReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
                [
                    'title' => __('admin.report_property_verification_title'),
                    'description' => __('admin.report_property_verification_desc'),
                    'url' => PropertyVerificationReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
            ],
            __('admin.financial') => [
                [
                    'title' => __('admin.report_revenue_title'),
                    'description' => __('admin.report_revenue_desc'),
                    'url' => RevenueReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
                [
                    'title' => __('admin.report_commission_title'),
                    'description' => __('admin.report_commission_desc'),
                    'url' => CommissionReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
                [
                    'title' => __('admin.report_tax_title'),
                    'description' => __('admin.report_tax_desc'),
                    'url' => TaxReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
                [
                    'title' => __('admin.report_refund_title'),
                    'description' => __('admin.report_refund_desc'),
                    'url' => RefundReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
                [
                    'title' => __('admin.report_transaction_title'),
                    'description' => __('admin.report_transaction_desc'),
                    'url' => TransactionReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
                [
                    'title' => __('admin.report_finance_summary_title'),
                    'description' => __('admin.report_finance_summary_desc'),
                    'url' => FinanceSummaryReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
            ],
            __('admin.feedback_and_rating') => [
                [
                    'title' => __('admin.report_feedback_rating_title'),
                    'description' => __('admin.report_feedback_rating_desc'),
                    'url' => FeedbackRatingReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
            ],
            __('admin.system_activity') => [
                [
                    'title' => __('admin.report_login_title'),
                    'description' => __('admin.report_login_desc'),
                    'url' => LoginReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
                [
                    'title' => __('admin.report_notification_title'),
                    'description' => __('admin.report_notification_desc'),
                    'url' => NotificationReport::getUrl(),
                    'icon' => 'heroicon-o-information-circle',
                ],
            ],
        ];
    }

    public function getFilteredSectionsProperty(): array
    {
        $sections = $this->reportsData;

        if ($this->activeTab !== 'All Reports') {
            if (isset($sections[$this->activeTab])) {
                $sections = [$this->activeTab => $sections[$this->activeTab]];
            } else {
                $sections = [];
            }
        }

        if (trim($this->search)) {
            $search = strtolower(trim($this->search));
            foreach ($sections as $key => $reports) {
                $filtered = array_filter($reports, function ($report) use ($search) {
                    return str_contains(strtolower($report['title']), $search) ||
                        str_contains(strtolower($report['description']), $search);
                });

                if (empty($filtered)) {
                    unset($sections[$key]);
                } else {
                    $sections[$key] = $filtered;
                }
            }
        }

        return $sections;
    }
}
