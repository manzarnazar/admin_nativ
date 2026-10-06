<?php

namespace App\Filament\Resources\LegalPolicies;

use App\Filament\Concerns\HasResourcePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\LegalPolicies\Pages\CreateLegalPolicy;
use App\Filament\Resources\LegalPolicies\Pages\EditLegalPolicy;
use App\Filament\Resources\LegalPolicies\Pages\ListLegalPolicies;
use App\Filament\Resources\LegalPolicies\Schemas\LegalPolicyForm;
use App\Filament\Resources\LegalPolicies\Tables\LegalPoliciesTable;
use App\Models\LegalPolicy;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class LegalPolicyResource extends Resource implements DeclaresTopbarControls
{
    use HasResourcePermission;

    protected static ?string $model = LegalPolicy::class;

    protected static ?string $slug = 'legal-policies';

    protected static ?int $navigationSort = 5;

    /**
     * Legal policies are general — hide both country and property switchers.
     *
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.legal_policies');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::ContentManagement);
    }

    public static function form(Schema $schema): Schema
    {
        return LegalPolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LegalPoliciesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalPolicies::route('/'),
            'create' => CreateLegalPolicy::route('/create'),
            'edit' => EditLegalPolicy::route('/{record}/edit'),
        ];
    }
}
