<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ResolvesRuleAnswerLabels;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Models\CancellationPolicy;
use App\Models\City;
use App\Models\FacilityCategory;
use App\Models\NearbyPlace;
use App\Models\Property;
use App\Models\PropertyRule;
use App\Models\User;
use App\Services\CancellationPolicyService;
use App\Support\Geo;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Url;

class PropertyView extends Page implements DeclaresTopbarControls
{
    use ResolvesRuleAnswerLabels;

    protected static ?string $slug = 'properties/{record}/view';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.property-view';

    public int $propertyId;

    #[Url(as: 'tab')]
    public string $activeTab = 'overview';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getProperty()->name;
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(int $record): void
    {
        $property = Property::query()->findOrFail($record);

        /** @var User $user */
        $user = auth()->user();

        if ($property->country_id !== $user->current_country_id) {
            $this->redirect(PropertyManage::getUrl());

            return;
        }

        $this->propertyId = $property->id;
    }

    public function getProperty(): Property
    {
        return Property::query()
            ->with([
                'country',
                'propertyType',
                'refState',
                'refCity',
                'facilities.category',
                'rooms.roomType.facilities',
                'ruleAnswers.question.propertyRule',
                'primaryImages',
                'galleryImages',
                'registrationValues.registrationField',
            ])
            ->withCount('reviews')
            ->withAvg('reviews as reviews_avg_rating', 'rating')
            ->findOrFail($this->propertyId);
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // ── Tab 2: Facilities ──────────────────────────────────────────────────

    public function getFacilitiesByCategory(): Collection
    {
        $property = $this->getProperty();
        $selectedIds = $property->facilities->pluck('id')->toArray();

        return FacilityCategory::query()
            ->where('status', 'active')
            ->with(['facilities' => fn ($q) => $q->where('status', 'active')->whereIn('facilities.id', $selectedIds)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($cat) => $cat->facilities->isNotEmpty());
    }

    // ── Tab 3: Property Rules ──────────────────────────────────────────────

    public function getPropertyRuleAnswers(): array
    {
        $property = $this->getProperty();
        $answers = $property->ruleAnswers;

        $rules = PropertyRule::query()
            ->where('status', 'active')
            ->applicableTo($property->country_id, $property->property_type_id)
            ->with(['questions' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        $grouped = [];

        foreach ($rules as $rule) {
            $ruleData = [
                'name' => $rule->name,
                'icon' => $rule->icon,
                'questions' => [],
            ];

            foreach ($rule->questions as $question) {
                $answer = $answers->firstWhere('property_rule_question_id', $question->id);
                $ruleData['questions'][] = [
                    'question' => $question->question_text,
                    'answer_type' => $question->answer_type->value,
                    'answer' => $this->resolveRuleAnswerLabel($question, $answer?->answer_value),
                ];
            }

            if (count($ruleData['questions']) > 0) {
                $grouped[] = $ruleData;
            }
        }

        return $grouped;
    }

    public function getCancellationPolicy(): ?CancellationPolicy
    {
        return app(CancellationPolicyService::class)->getPolicyForProperty($this->getProperty());
    }

    // ── Tab 4: Nearby Places ───────────────────────────────────────────────

    public function getNearbyPlaces(): Collection
    {
        $property = $this->getProperty();

        if (! $property->ref_city_id) {
            return new Collection;
        }

        $city = City::query()
            ->where('ref_city_id', $property->ref_city_id)
            ->first();

        if (! $city) {
            return new Collection;
        }

        $query = NearbyPlace::query()
            ->where('city_id', $city->id)
            ->with('nearbyPlaceCategory');

        // Distance is specific to this property's exact coordinates, not the
        // city as a whole, so it's computed here rather than stored on the row.
        if ($property->latitude && $property->longitude) {
            $query->select('nearby_places.*')
                ->addSelect(Geo::haversineExpression((float) $property->latitude, (float) $property->longitude))
                ->orderBy('distance_km');
        } else {
            $query->orderBy('name');
        }

        return $query->get();
    }

    // ── Tab 5: Media ───────────────────────────────────────────────────────

    public function getGalleryGroups(): array
    {
        $property = $this->getProperty();

        return $property->galleryImages
            ->groupBy('group_name')
            ->map(fn ($images, $name) => [
                'name' => $name,
                'count' => $images->count(),
                'images' => $images->pluck('image_path')->toArray(),
            ])
            ->values()
            ->toArray();
    }

    // ── Tab 6: Documents ───────────────────────────────────────────────────

    public function getDocuments(): array
    {
        $property = $this->getProperty();

        return $property->registrationValues
            ->filter(fn ($val) => $val->registrationField && $val->registrationField->field_type->value === 'file_upload')
            ->map(fn ($val) => [
                'name' => $val->registrationField->name,
                'path' => is_array($val->value) ? ($val->value[0] ?? null) : $val->value,
                'uploaded_at' => $val->updated_at,
            ])
            ->values()
            ->toArray();
    }

    public function getDocumentCount(): int
    {
        return count($this->getDocuments());
    }
}
