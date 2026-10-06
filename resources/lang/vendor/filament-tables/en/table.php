<?php
return [
    'filters' => [
        'indicator' => 'Active filters',
        'actions' => [
            'remove' => [
                'label' => 'Remove filter',
            ],
            'remove_all' => [
                'tooltip' => 'Remove all filters',
            ],
        ],
    ],
    'fields' => [
        'search' => [
            'indicator' => 'Search',
        ],
    ],
    'summary' => [
        // Default is "Summary" — reports (and any future table using ->summaries()) show this as
        // the leftmost label on the totals row, so "Total" matches that context better everywhere.
        'heading' => 'Total',
    ],
];
