import flatpickr from 'flatpickr';

// Report pages: a single-field date-range filter (Flatpickr's own `range` mode) instead of two
// separate date inputs. Flatpickr manages both dates internally and writes the whole range into
// one input as "Jul 1, 2026 to Aug 6, 2026" — parsed server-side via Carbon::parse() in the
// filter's ->query(), so there is no native-input DOM to fight and no Livewire state to hand-sync
// beyond a single text value.
//
// Reusable across reports: give any TextInput `->extraInputAttributes(['data-flatpickr-range' => 'true'])`
// and this auto-mounts on it — no per-report JS. See HasReportPageConventions::dateRangeFilter().
const RANGE_SEPARATOR = ' to ';

// input -> flatpickr instance, so the polling loop below can re-sync Flatpickr's displayed value
// when Livewire changes input.value from outside (e.g. "Reset filters") — MutationObserver can't
// see this, since setting the .value *property* (what Livewire's morph does) never touches the
// value *attribute* that MutationObserver watches.
const instances = new Map();

function initDateRangeFilters() {
    document.querySelectorAll('[data-flatpickr-range]').forEach(input => {
        if (instances.has(input)) {
            return;
        }

        const instance = flatpickr(input, {
            // No altInput: that creates a separate, Flatpickr-styled <input> alongside the
            // original one, which doesn't inherit Filament's bordered/padded input styling —
            // it would look visually inconsistent with the Property Type/City/Partner fields
            // next to it. Attaching directly to the same Filament-rendered input instead means
            // it automatically keeps that exact styling, with dateFormat as the pretty display
            // Carbon::parse() on the server reads just as easily as an ISO date.
            mode: 'range',
            dateFormat: 'M j, Y',
            locale: { rangeSeparator: RANGE_SEPARATOR },
            onClose(selectedDates) {
                // Only commit to Livewire once both ends of the range are picked — avoids firing
                // a half-finished range while the user is still selecting the second date.
                if (selectedDates.length !== 2) {
                    return;
                }

                // Flatpickr already writes the formatted range into `input.value` at this point,
                // but dispatch explicitly rather than assume Livewire's wire:model is listening
                // for whatever event Flatpickr fires internally.
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            },
        });

        instances.set(input, instance);
    });
}

function resyncDateRangeFilters() {
    instances.forEach((instance, input) => {
        if (!document.contains(input)) {
            instance.destroy();
            instances.delete(input);

            return;
        }

        const displayed = instance.selectedDates.length === 2
            ? instance.selectedDates.map(d => instance.formatDate(d, 'M j, Y')).join(RANGE_SEPARATOR)
            : '';

        if (displayed === input.value) {
            return;
        }

        if (!input.value) {
            instance.clear();

            return;
        }

        const [from, to] = input.value.split(RANGE_SEPARATOR);

        if (from && to) {
            instance.setDate([from, to], false);
        }
    });
}

document.addEventListener('DOMContentLoaded', initDateRangeFilters);
document.addEventListener('livewire:navigated', initDateRangeFilters);
setInterval(() => {
    initDateRangeFilters();
    resyncDateRangeFilters();
}, 400);
