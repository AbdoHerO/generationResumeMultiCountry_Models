<?php

declare(strict_types=1);

return [
    'name' => '简体中文',
    'dir' => 'ltr',
    'sections' => [
        'summary' => '个人简介',
        'experience' => '工作经历',
        'education' => '教育背景',
        'skills' => '专业技能',
        'languages' => '语言能力',
        'certifications' => '证书',
        'projects' => '项目经历',
        'internships' => '实习经历',
        'volunteer' => '志愿者经历',
        'awards' => '获奖情况',
        'publications' => '发表论文',
        'interests' => '专业兴趣',
        'hobbies' => '兴趣爱好',
        'references' => '推荐人',
        'additional' => '其他信息',
        'custom' => '其他',
        'contact' => '联系方式',
    ],
    'labels' => [
        'present' => '至今',
        'email' => '邮箱',
        'phone' => '电话',
        'address' => '地址',
        'website' => '个人网站',
        'linkedin' => '领英',
        'portfolio' => '作品集',
        'dateOfBirth' => '出生年月',
        'nationality' => '国籍',
        'workAuthorization' => '工作许可',
        'drivingLicense' => '驾驶证',
        'hometown' => '籍贯',
        'technical' => '技术能力',
        'soft' => '综合素质',
        'tools' => '工具',
        'grade' => '成绩',
        'references_on_request' => '如有需要，可提供推荐人信息。',
        'skills_core' => '核心能力',
        'transferable' => '可迁移技能',
    ],
    'months' => ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
    'levels' => [
        'beginner' => '入门', 'intermediate' => '中级', 'advanced' => '高级', 'expert' => '精通',
        'native' => '母语', 'fluent' => '流利', 'professional' => '工作熟练', 'conversational' => '日常交流', 'basic' => '基础',
    ],
    'employment_types' => [
        'full_time' => '全职', 'part_time' => '兼职', 'contract' => '合同制', 'freelance' => '自由职业',
        'internship' => '实习', 'apprenticeship' => '学徒', 'temporary' => '临时',
    ],
    // Chinese dates: 2021年3月 or 2021.03
    'date_formats' => ['month_year' => '{Y}年{N}月', 'numeric' => '{Y}.{MM}', 'year' => '{Y}年'],
];
