<?php

namespace App\Livewire;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CommandPalette extends Component
{
    public string $search = '';

    #[Computed]
    public function results(): array
    {
        $query = mb_strtolower(trim($this->search));
        $groups = [];

        foreach (Filament::getNavigation() as $group) {
            $groupLabel = $group->getLabel() ?? '';
            $matchedItems = [];

            foreach ($group->getItems() as $item) {
                $label = $item->getLabel();

                if (
                    $query === ''
                    || str_contains(mb_strtolower($label), $query)
                    || str_contains(mb_strtolower($groupLabel), $query)
                ) {
                    $matchedItems[] = [
                        'label' => $label,
                        'url' => $item->getUrl(),
                        'icon' => $item->getIcon(),
                        'is_child' => false,
                    ];
                }

                foreach ($item->getChildItems() as $child) {
                    $childLabel = $child->getLabel();

                    if (
                        $query === ''
                        || str_contains(mb_strtolower($childLabel), $query)
                        || str_contains(mb_strtolower($groupLabel), $query)
                        || str_contains(mb_strtolower($label), $query)
                    ) {
                        $matchedItems[] = [
                            'label' => $childLabel,
                            'url' => $child->getUrl(),
                            'icon' => $child->getIcon() ?? $item->getIcon(),
                            'is_child' => true,
                        ];
                    }
                }
            }

            if (! empty($matchedItems)) {
                $groups[] = [
                    'label' => $groupLabel,
                    'icon' => $group->getIcon(),
                    'items' => $matchedItems,
                ];
            }
        }

        return $groups;
    }

    public function render(): View
    {
        return view('livewire.command-palette');
    }
}
