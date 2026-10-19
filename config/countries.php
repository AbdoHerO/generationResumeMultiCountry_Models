<?php

declare(strict_types=1);

/*
 * Country rules. Everything country-specific lives here (or in the admin overrides stored in
 * the settings table), never as scattered conditions in the code.
 *
 * optional_fields: personal details offered in the editor for this country. They are always
 *                  optional and hidden from the CV until the user fills them in.
 * photo:           'common' | 'optional' | 'discouraged' (guidance shown in the editor).
 * section_order:   default order of sections for a new CV.
 */
return [
    'FR' => [
        'name' => 'France',
        'native' => 'France',
        'languages' => ['fr', 'en'],
        'default_language' => 'fr',
        'phone_code' => '+33',
        'phone_example' => '+33 6 12 34 56 78',
        'date_style' => 'month_year',
        'page_target' => 'one',
        'length_hint' => 'One page is the norm; two pages for 10+ years of experience.',
        'photo' => 'optional',
        'optional_fields' => ['dateOfBirth', 'nationality', 'drivingLicense'],
        'section_order' => ['summary', 'experience', 'education', 'skills', 'languages', 'certifications', 'interests'],
        'summary' => 'A concise, one-page CV with a short professional profile, experience in reverse chronological order, education, skills and languages.',
        'tips' => [
            'Write a 3–4 line "Profil professionnel" that matches the job offer.',
            'Photo, date of birth and nationality are optional. Leave them out unless they are relevant.',
            'Mention your language levels with the CEFR scale (A1–C2) when you can.',
        ],
    ],
    'MA' => [
        'name' => 'Morocco',
        'native' => 'Maroc · المغرب',
        'languages' => ['fr', 'ar', 'en'],
        'default_language' => 'fr',
        'phone_code' => '+212',
        'phone_example' => '+212 6 12 34 56 78',
        'date_style' => 'month_year',
        'page_target' => 'one',
        'length_hint' => 'One page for most profiles; two pages for experienced candidates.',
        'photo' => 'common',
        'optional_fields' => ['dateOfBirth', 'nationality', 'drivingLicense'],
        'section_order' => ['summary', 'education', 'experience', 'skills', 'languages', 'certifications', 'interests'],
        'summary' => 'French is the usual language of Moroccan CVs; Arabic (right-to-left) and English are fully supported. Education is often presented first for early-career profiles.',
        'tips' => [
            'French is the default; switch the document language to Arabic for a right-to-left CV.',
            'List your languages with their level (Arabic, French, English, Amazigh…).',
            'A professional photo is common but never required.',
        ],
    ],
    'US' => [
        'name' => 'United States',
        'native' => 'United States',
        'languages' => ['en'],
        'default_language' => 'en',
        'phone_code' => '+1',
        'phone_example' => '+1 (415) 555-0132',
        'date_style' => 'month_year',
        'page_target' => 'one',
        'length_hint' => 'One page for under 10 years of experience; two pages maximum.',
        'photo' => 'discouraged',
        'optional_fields' => ['workAuthorization'],
        'section_order' => ['summary', 'experience', 'skills', 'education', 'projects', 'certifications'],
        'summary' => 'An achievement-oriented resume: a concise summary, quantified accomplishments, skills and education. No photo, date of birth or marital status.',
        'tips' => [
            'Start each bullet with an action verb and quantify results (%, $, time saved).',
            'Leave out the photo, date of birth, marital status and nationality.',
            'Keep it to one page unless you have extensive relevant experience.',
        ],
    ],
    'CA' => [
        'name' => 'Canada',
        'native' => 'Canada',
        'languages' => ['en', 'fr'],
        'default_language' => 'en',
        'phone_code' => '+1',
        'phone_example' => '+1 (514) 555-0147',
        'date_style' => 'month_year',
        'page_target' => 'two',
        'length_hint' => 'Up to two pages; focus on relevant skills and accomplishments.',
        'photo' => 'discouraged',
        'optional_fields' => ['workAuthorization'],
        'section_order' => ['summary', 'skills', 'experience', 'education', 'certifications', 'volunteer', 'languages'],
        'summary' => 'A clear, concise resume in English or French focused on skills, accomplishments and relevant experience. Photos and personal details are left out.',
        'tips' => [
            'Mention your work authorization (citizen, permanent resident, work permit) if it helps.',
            'Volunteer experience is valued; add it as its own section.',
            'Bilingual? Add English and French to your languages with their level.',
        ],
    ],
    'CN' => [
        'name' => 'China',
        'native' => '中国',
        'languages' => ['zh', 'en'],
        'default_language' => 'zh',
        'phone_code' => '+86',
        'phone_example' => '+86 138 0013 8000',
        'date_style' => 'numeric',
        'page_target' => 'one',
        'length_hint' => 'One page is preferred; two pages for senior profiles.',
        'photo' => 'common',
        'optional_fields' => ['dateOfBirth', 'nationality', 'hometown'],
        'section_order' => ['summary', 'education', 'experience', 'projects', 'skills', 'awards', 'languages'],
        'summary' => 'Simplified Chinese by default (English available), with clear education details, projects and awards. Personal details such as date of birth are optional.',
        'tips' => [
            '简历一般控制在一页，突出与职位相关的经历。',
            'Education usually comes first for students and recent graduates.',
            'Personal details (出生年月, 籍贯) are optional; include them only if useful.',
        ],
    ],
];
