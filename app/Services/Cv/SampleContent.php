<?php

declare(strict_types=1);

namespace App\Services\Cv;

/**
 * Fictional, realistic sample CVs used to preview templates in the gallery and by the
 * development-only demo seeder. Never shown as a user's saved data.
 */
final class SampleContent
{
    /** @return array<string, mixed> */
    public static function for(string $country, string $lang): array
    {
        $raw = match ($lang) {
            'fr' => self::french($country),
            'ar' => self::arabic(),
            'zh' => self::chinese(),
            default => self::english($country),
        };
        return CvSchema::sanitize($raw);
    }

    /** @param list<array<string, mixed>> $sections @param array<string, mixed> $personal @return array<string, mixed> */
    private static function build(array $personal, array $sections): array
    {
        $out = [];
        foreach ($sections as $s) {
            $out[] = $s + ['id' => CvSchema::newId('s'), 'title' => '', 'hidden' => false];
        }
        return ['personal' => $personal + ['links' => []], 'sections' => $out];
    }

    /** @return array<string, mixed> */
    private static function french(string $country): array
    {
        $ma = $country === 'MA';
        $ca = $country === 'CA';
        return self::build([
            'fullName' => $ma ? 'Yasmine El Amrani' : ($ca ? 'Émilie Gagnon' : 'Camille Laurent'),
            'headline' => $ma ? 'Ingénieure Data & Business Intelligence' : 'Développeuse Full-Stack · PHP / JavaScript',
            'email' => $ma ? 'yasmine.elamrani@example.ma' : ($ca ? 'emilie.gagnon@example.ca' : 'camille.laurent@example.fr'),
            'phone' => $ma ? '+212 6 61 23 45 67' : ($ca ? '+1 (514) 555-0147' : '+33 6 12 34 56 78'),
            'city' => $ma ? 'Casablanca' : ($ca ? 'Montréal' : 'Lyon'),
            'country' => $ma ? 'Maroc' : ($ca ? 'Canada' : 'France'),
            'linkedin' => 'https://www.linkedin.com/in/' . ($ma ? 'yasmine-elamrani' : ($ca ? 'emilie-gagnon' : 'camille-laurent')),
            'portfolio' => $ma ? '' : 'https://github.com/' . ($ca ? 'egagnon' : 'claurent'),
        ], [
            ['type' => 'summary', 'text' => $ma
                ? 'Ingénieure data avec 5 ans d’expérience dans la banque et les télécoms. Je conçois des pipelines de données fiables et des tableaux de bord qui aident les équipes métier à décider plus vite.'
                : 'Développeuse full-stack avec 6 ans d’expérience dans le e-commerce et les services publics numériques. J’aime concevoir des applications robustes, accessibles et faciles à maintenir.'],
            ['type' => 'experience', 'items' => [
                ['title' => $ma ? 'Ingénieure Data' : 'Développeuse Full-Stack senior', 'organization' => $ma ? 'Banque Atlantique du Maroc' : 'Maison Verte (e-commerce)',
                    'location' => $ma ? 'Casablanca' : 'Lyon', 'employmentType' => 'full_time', 'startDate' => '2021-03', 'current' => true,
                    'bullets' => $ma
                        ? ['Mise en place d’un entrepôt de données pour 12 agences, temps de reporting réduit de 70 %.', 'Automatisation de 35 rapports réglementaires avec Python et Airflow.', 'Formation de 20 analystes à Power BI.']
                        : ['Refonte du tunnel de commande : taux de conversion +18 % en six mois.', 'Migration de l’API vers PHP 8.3 et mise en place de tests automatisés (couverture 82 %).', 'Encadrement de deux développeurs juniors.']],
                ['title' => $ma ? 'Analyste BI' : 'Développeuse PHP', 'organization' => $ma ? 'Maroc Télécom Services' : 'Agence Numérique de la Région',
                    'location' => $ma ? 'Rabat' : 'Grenoble', 'employmentType' => 'full_time', 'startDate' => '2018-09', 'endDate' => '2021-02',
                    'bullets' => $ma
                        ? ['Création de tableaux de bord de suivi client utilisés par 300 conseillers.', 'Fiabilisation des données de facturation (anomalies −40 %).']
                        : ['Développement d’un portail de démarches en ligne utilisé par 150 000 habitants.', 'Mise en conformité RGAA (accessibilité) de 40 formulaires.']],
            ]],
            ['type' => 'education', 'items' => [
                ['degree' => $ma ? 'Diplôme d’ingénieur d’État — Génie informatique' : 'Master Informatique — Génie logiciel', 'institution' => $ma ? 'ENSIAS, Université Mohammed V' : 'Université Claude Bernard Lyon 1',
                    'location' => $ma ? 'Rabat' : 'Lyon', 'startDate' => '2015-09', 'endDate' => '2018-07', 'grade' => $ma ? 'Bien' : 'Très bien'],
                ['degree' => $ma ? 'Classes préparatoires MP' : 'Licence Informatique', 'institution' => $ma ? 'CPGE Moulay Youssef' : 'Université Grenoble Alpes',
                    'location' => $ma ? 'Rabat' : 'Grenoble', 'startDate' => '2013-09', 'endDate' => '2015-06'],
            ]],
            ['type' => 'skills', 'items' => array_merge(
                array_map(fn ($n) => ['name' => $n, 'category' => 'technical'], $ma ? ['Python', 'SQL', 'Power BI', 'Apache Airflow', 'Spark'] : ['PHP 8', 'Laravel', 'JavaScript', 'MySQL', 'Docker']),
                array_map(fn ($n) => ['name' => $n, 'category' => 'soft'], ['Communication', 'Esprit d’équipe', 'Autonomie'])
            )],
            ['type' => 'languages', 'items' => $ma
                ? [['name' => 'Arabe', 'level' => 'native'], ['name' => 'Français', 'level' => 'fluent'], ['name' => 'Anglais', 'level' => 'B2']]
                : [['name' => 'Français', 'level' => 'native'], ['name' => 'Anglais', 'level' => 'C1'], ['name' => 'Espagnol', 'level' => 'B1']]],
            ['type' => 'certifications', 'items' => [
                ['name' => $ma ? 'Microsoft Certified: Power BI Data Analyst' : 'AWS Certified Developer – Associate', 'issuer' => $ma ? 'Microsoft' : 'Amazon Web Services', 'date' => '2023-05'],
            ]],
            ['type' => 'interests', 'items' => [['name' => 'Course à pied'], ['name' => 'Photographie'], ['name' => 'Bénévolat associatif']]],
        ]);
    }

    /** @return array<string, mixed> */
    private static function english(string $country): array
    {
        $who = match ($country) {
            'CA' => ['Sarah Thompson', 'Toronto, ON', 'Canada', '+1 (416) 555-0198', 'sarah.thompson@example.ca', 'Maple Logistics', 'Toronto', 'University of Waterloo'],
            'FR' => ['Thomas Moreau', 'Paris', 'France', '+33 6 98 76 54 32', 'thomas.moreau@example.fr', 'Atelier Digital', 'Paris', 'École Polytechnique'],
            'MA' => ['Omar Bennani', 'Rabat', 'Morocco', '+212 6 70 11 22 33', 'omar.bennani@example.ma', 'Atlas Fintech', 'Casablanca', 'Al Akhawayn University'],
            'CN' => ['Ming Li', 'Shanghai', 'China', '+86 138 0013 8000', 'ming.li@example.cn', 'Orient Cloud Technologies', 'Shanghai', 'Fudan University'],
            default => ['Jordan Mitchell', 'Austin, TX', 'United States', '+1 (512) 555-0132', 'jordan.mitchell@example.com', 'Northwind Analytics', 'Austin, TX', 'The University of Texas at Austin'],
        };
        return self::build([
            'fullName' => $who[0], 'headline' => 'Senior Software Engineer · Cloud & Data Platforms', 'email' => $who[4], 'phone' => $who[3],
            'city' => $who[1], 'country' => $who[2], 'linkedin' => 'https://www.linkedin.com/in/' . strtolower(str_replace(' ', '-', $who[0])),
            'portfolio' => 'https://github.com/' . strtolower(str_replace(' ', '', $who[0])),
        ], [
            ['type' => 'summary', 'text' => 'Software engineer with 7 years of experience building reliable cloud services and data platforms. Led teams that cut infrastructure costs by 30% and shipped products used by millions. Known for clear communication and pragmatic engineering.'],
            ['type' => 'experience', 'items' => [
                ['title' => 'Senior Software Engineer', 'organization' => $who[5], 'location' => $who[6], 'employmentType' => 'full_time', 'startDate' => '2021-01', 'current' => true,
                    'bullets' => ['Designed an event-driven data platform processing 40M events per day with 99.95% availability.', 'Reduced cloud spend by $420K per year by right-sizing clusters and adding autoscaling.', 'Mentored 5 engineers; introduced code review guidelines adopted company-wide.']],
                ['title' => 'Software Engineer', 'organization' => 'Brightpath Health', 'location' => $who[6], 'employmentType' => 'full_time', 'startDate' => '2017-06', 'endDate' => '2020-12',
                    'bullets' => ['Built patient scheduling APIs serving 1.2M monthly users.', 'Cut page load time by 45% through caching and query optimization.']],
            ]],
            ['type' => 'skills', 'items' => array_merge(
                array_map(fn ($n) => ['name' => $n, 'category' => 'technical'], ['Python', 'Go', 'TypeScript', 'PostgreSQL', 'Kubernetes', 'AWS']),
                array_map(fn ($n) => ['name' => $n, 'category' => 'soft'], ['Technical leadership', 'Stakeholder communication'])
            )],
            ['type' => 'education', 'items' => [
                ['degree' => 'B.S. in Computer Science', 'institution' => $who[7], 'location' => $who[1], 'startDate' => '2013-09', 'endDate' => '2017-05', 'grade' => 'GPA 3.8/4.0'],
            ]],
            ['type' => 'projects', 'items' => [
                ['name' => 'Open-source query profiler', 'role' => 'Maintainer', 'url' => 'https://github.com/example/qprof', 'startDate' => '2022-02', 'current' => true,
                    'description' => 'A lightweight SQL profiler with 2,300 GitHub stars, used in production by 40+ companies.'],
            ]],
            ['type' => 'certifications', 'items' => [['name' => 'AWS Certified Solutions Architect – Professional', 'issuer' => 'Amazon Web Services', 'date' => '2023-03']]],
            ['type' => 'languages', 'items' => [['name' => 'English', 'level' => 'native'], ['name' => $country === 'CA' ? 'French' : 'Spanish', 'level' => 'professional']]],
        ]);
    }

    /** @return array<string, mixed> */
    private static function arabic(): array
    {
        return self::build([
            'fullName' => 'ياسمين العمراني', 'headline' => 'مهندسة بيانات وذكاء الأعمال', 'email' => 'yasmine.elamrani@example.ma', 'phone' => '+212 6 61 23 45 67',
            'city' => 'الدار البيضاء', 'country' => 'المغرب', 'linkedin' => 'https://www.linkedin.com/in/yasmine-elamrani',
        ], [
            ['type' => 'summary', 'text' => 'مهندسة بيانات بخبرة خمس سنوات في القطاع البنكي والاتصالات. أصمم أنظمة بيانات موثوقة ولوحات قيادة تساعد الفرق على اتخاذ قرارات أسرع.'],
            ['type' => 'experience', 'items' => [
                ['title' => 'مهندسة بيانات', 'organization' => 'البنك الأطلسي للمغرب', 'location' => 'الدار البيضاء', 'startDate' => '2021-03', 'current' => true,
                    'bullets' => ['إنشاء مستودع بيانات لـ 12 وكالة وتقليص مدة إعداد التقارير بنسبة 70 %.', 'أتمتة 35 تقريرا تنظيميا باستخدام Python و Airflow.']],
                ['title' => 'محللة ذكاء الأعمال', 'organization' => 'خدمات اتصالات المغرب', 'location' => 'الرباط', 'startDate' => '2018-09', 'endDate' => '2021-02',
                    'bullets' => ['تصميم لوحات قيادة لتتبع العملاء يستعملها 300 مستشار.']],
            ]],
            ['type' => 'education', 'items' => [
                ['degree' => 'دبلوم مهندس دولة في الهندسة المعلوماتية', 'institution' => 'المدرسة الوطنية العليا للمعلوميات وتحليل النظم', 'location' => 'الرباط', 'startDate' => '2015-09', 'endDate' => '2018-07'],
            ]],
            ['type' => 'skills', 'items' => array_map(fn ($n) => ['name' => $n, 'category' => 'technical'], ['Python', 'SQL', 'Power BI', 'Apache Spark'])],
            ['type' => 'languages', 'items' => [['name' => 'العربية', 'level' => 'native'], ['name' => 'الفرنسية', 'level' => 'fluent'], ['name' => 'الإنجليزية', 'level' => 'B2']]],
        ]);
    }

    /** @return array<string, mixed> */
    private static function chinese(): array
    {
        return self::build([
            'fullName' => '李明', 'headline' => '高级软件工程师 · 云计算与数据平台', 'email' => 'ming.li@example.cn', 'phone' => '+86 138 0013 8000',
            'city' => '上海', 'country' => '中国', 'portfolio' => 'https://github.com/liming',
        ], [
            ['type' => 'summary', 'text' => '七年后端开发经验，专注于高并发分布式系统与数据平台建设。曾带领团队将系统成本降低30%，具备良好的沟通能力和责任心。'],
            ['type' => 'education', 'items' => [
                ['degree' => '计算机科学与技术 硕士', 'institution' => '复旦大学', 'location' => '上海', 'startDate' => '2014-09', 'endDate' => '2017-06', 'grade' => 'GPA 3.7/4.0'],
                ['degree' => '软件工程 学士', 'institution' => '华中科技大学', 'location' => '武汉', 'startDate' => '2010-09', 'endDate' => '2014-06'],
            ]],
            ['type' => 'experience', 'items' => [
                ['title' => '高级软件工程师', 'organization' => '东方云科技有限公司', 'location' => '上海', 'startDate' => '2020-07', 'current' => true,
                    'bullets' => ['负责电商平台后端架构设计，支撑日均订单量超过一百万。', '主导微服务改造，系统可用性提升至99.95%。', '指导5名初级工程师，建立代码评审规范。']],
                ['title' => '软件工程师', 'organization' => '杭州数智网络有限公司', 'location' => '杭州', 'startDate' => '2017-07', 'endDate' => '2020-06',
                    'bullets' => ['开发支付对账系统，处理效率提升40%。']],
            ]],
            ['type' => 'projects', 'items' => [
                ['name' => '实时数据分析平台', 'role' => '技术负责人', 'startDate' => '2022-03', 'endDate' => '2023-01', 'description' => '基于 Kafka 与 Flink 构建实时数据管道，延迟降低至秒级。'],
            ]],
            ['type' => 'skills', 'items' => array_merge(
                array_map(fn ($n) => ['name' => $n, 'category' => 'technical'], ['Java', 'Go', 'MySQL', 'Redis', 'Kubernetes']),
                array_map(fn ($n) => ['name' => $n, 'category' => 'soft'], ['团队协作', '沟通能力'])
            )],
            ['type' => 'awards', 'items' => [['title' => '年度优秀员工', 'issuer' => '东方云科技有限公司', 'date' => '2022-12']]],
            ['type' => 'languages', 'items' => [['name' => '中文', 'level' => 'native'], ['name' => '英语', 'level' => 'CET-6']]],
        ]);
    }
}
