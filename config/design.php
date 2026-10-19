<?php

declare(strict_types=1);

/*
 * Curated document design options. The same font files are used by the browser preview
 * (public/assets/fonts, @font-face) and by mPDF, so the preview matches the PDF.
 */
return [
    'palette' => [
        '#1F3A5F' => 'Navy',
        '#1D4ED8' => 'Royal blue',
        '#4338CA' => 'Indigo',
        '#0F766E' => 'Teal',
        '#047857' => 'Emerald',
        '#9F1239' => 'Burgundy',
        '#B45309' => 'Amber',
        '#6D28D9' => 'Plum',
        '#334155' => 'Slate',
        '#111827' => 'Graphite',
    ],

    // key => [label, css family, mPDF font key, type, files]
    'fonts' => [
        'lato' => [
            'label' => 'Lato', 'css' => 'Lato', 'type' => 'sans',
            'files' => ['R' => 'Lato-Regular.ttf', 'B' => 'Lato-Bold.ttf', 'I' => 'Lato-Italic.ttf', 'BI' => 'Lato-BoldItalic.ttf'],
        ],
        'firasans' => [
            'label' => 'Fira Sans', 'css' => 'Fira Sans', 'type' => 'sans',
            'files' => ['R' => 'FiraSans-Regular.ttf', 'B' => 'FiraSans-Bold.ttf', 'I' => 'FiraSans-Italic.ttf', 'BI' => 'FiraSans-BoldItalic.ttf'],
        ],
        'ptsans' => [
            'label' => 'PT Sans', 'css' => 'PT Sans', 'type' => 'sans',
            'files' => ['R' => 'PT_Sans-Web-Regular.ttf', 'B' => 'PT_Sans-Web-Bold.ttf', 'I' => 'PT_Sans-Web-Italic.ttf', 'BI' => 'PT_Sans-Web-BoldItalic.ttf'],
        ],
        'ptserif' => [
            'label' => 'PT Serif', 'css' => 'PT Serif', 'type' => 'serif',
            'files' => ['R' => 'PT_Serif-Web-Regular.ttf', 'B' => 'PT_Serif-Web-Bold.ttf', 'I' => 'PT_Serif-Web-Italic.ttf', 'BI' => 'PT_Serif-Web-BoldItalic.ttf'],
        ],
        'plexserif' => [
            'label' => 'IBM Plex Serif', 'css' => 'IBM Plex Serif', 'type' => 'serif',
            'files' => ['R' => 'IBMPlexSerif-Regular.ttf', 'B' => 'IBMPlexSerif-Bold.ttf', 'I' => 'IBMPlexSerif-Italic.ttf', 'BI' => 'IBMPlexSerif-BoldItalic.ttf'],
        ],
        'plexmono' => [
            'label' => 'IBM Plex Mono', 'css' => 'IBM Plex Mono', 'type' => 'mono',
            'files' => ['R' => 'IBMPlexMono-Regular.ttf', 'B' => 'IBMPlexMono-Bold.ttf'],
        ],
    ],

    // Fonts a user can pick for the body text (mono stays an accent font).
    'selectable_fonts' => ['lato', 'firasans', 'ptsans', 'ptserif', 'plexserif'],

    'font_size' => ['min' => 9.0, 'max' => 12.0, 'step' => 0.5],
    'line_height' => ['min' => 1.15, 'max' => 1.6],

    // Page margins in millimetres.
    'margins' => ['narrow' => 12, 'normal' => 16, 'wide' => 21],
    // Space before a section heading, in millimetres.
    'spacing' => ['compact' => 3.5, 'normal' => 5.5, 'relaxed' => 7.5],

    'date_styles' => ['month_year', 'numeric', 'year'],
    'page_targets' => ['auto', 'one', 'two'],
];
