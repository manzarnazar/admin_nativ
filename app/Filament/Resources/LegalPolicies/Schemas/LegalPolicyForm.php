<?php

namespace App\Filament\Resources\LegalPolicies\Schemas;

use App\Enums\PolicyType;
use App\Models\Language;
use App\Models\LegalPolicy;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class LegalPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(2)->schema([
                    Select::make('type')
                        ->label(__('admin.policy_type'))
                        ->placeholder(__('admin.select_policy_type'))
                        ->options(
                            collect(PolicyType::cases())
                                ->when(! SystemMode::isMulti(), fn ($c) => $c->filter(fn (PolicyType $t) => $t !== PolicyType::PartnerPolicy))
                                ->mapWithKeys(fn (PolicyType $type) => [$type->value => $type->label()])
                        )
                        ->required()
                        ->unique(
                            table: LegalPolicy::class,
                            column: 'type',
                            ignoreRecord: true,
                            modifyRuleUsing: fn ($rule, $get) => $rule->where('language_id', $get('language_id'))->whereNull('deleted_at'),
                        )
                        ->validationMessages(['unique' => __('admin.this_policy_type_already_exists_for_the_selected_language')]),
                    Select::make('language_id')
                        ->label(__('admin.language'))
                        ->placeholder(__('admin.select_language'))
                        ->options(Language::query()->where('status', true)->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                ]),
                Section::make(__('admin.policy_sections'))
                    ->headerActions([
                        Action::make('addSection')
                            ->label(__('admin.add_section'))
                            ->icon('heroicon-o-plus')
                            ->extraAttributes(['style' => 'background-color: #111827; color: #fff; border-color: #111827;'])
                            ->action(function ($get, $set): void {
                                $sections = $get('sections') ?? [];
                                $sections[Str::uuid()->toString()] = ['title' => '', 'content' => ''];
                                $set('sections', $sections);
                            }),
                    ])
                    ->schema([
                        Repeater::make('sections')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('title')
                                    ->label(__('admin.section_title'))
                                    ->placeholder(__('admin.eg_information_we_collect'))
                                    ->required()
                                    ->maxLength(255),
                                RichEditor::make('content')
                                    ->label(__('admin.section_content'))
                                    ->placeholder(__('admin.add_section_content'))
                                    ->required()
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'underline',
                                        'strike',
                                        'bulletList',
                                        'orderedList',
                                        'link',
                                        'undo',
                                        'redo',
                                    ]),
                            ])
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addAction(fn (Action $action) => $action->hidden()),
                    ]),
                Radio::make('is_active')
                    ->label(__('admin.status'))
                    ->boolean(
                        trueLabel: __('admin.active'),
                        falseLabel: __('admin.inactive'),
                    )
                    ->default(true)
                    ->required()
                    ->inline(),

                Section::make(__('admin.seo_settings'))
                    ->schema([
                        FileUpload::make('og_image')
                            ->label(__('admin.og_image'))
                            ->disk('public')
                            ->directory('legal-policies/seo')
                            ->image()
                            ->maxSize(1024)
                            ->helperText(__('admin.maximum_size_1mb')),
                        TextInput::make('meta_title')
                            ->label(__('admin.meta_title'))
                            ->maxLength(255),
                        Textarea::make('meta_description')
                            ->label(__('admin.meta_description'))
                            ->rows(3),
                        Textarea::make('meta_keyword')
                            ->label(__('admin.meta_keywords'))
                            ->placeholder(__('admin.eg_hotel_travel_luxury'))
                            ->rows(2),
                        Textarea::make('schema_markup')
                            ->label(__('admin.schema_markup'))
                            ->placeholder(__('admin.enter_schema_markup'))
                            ->rows(5),
                    ]),
            ]);
    }
}
