<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Template;
use App\Services\Auth;
use App\Services\Countries;

final class AdminTemplateController extends Controller
{
    public const CATEGORIES = ['classic', 'modern', 'executive', 'creative', 'compact', 'academic', 'bilingual', 'technical', 'entry-level', 'career'];

    public function index(Request $request): Response
    {
        $usage = [];
        foreach (\App\Core\Database::all('SELECT template_id, COUNT(*) AS n FROM cvs GROUP BY template_id') as $row) {
            $usage[(int) $row['template_id']] = (int) $row['n'];
        }
        return $this->view('admin/templates', [
            'title' => 'Templates',
            'templates' => Template::all(false),
            'usage' => $usage,
            'countries' => Countries::all(),
            'categories' => self::CATEGORIES,
            'country' => strtoupper($request->string('country')),
        ], 'layouts/admin');
    }

    public function update(Request $request): Response
    {
        $template = Template::find((int) $request->param('id'));
        if (!$template) {
            throw new HttpException(404);
        }
        $countryLangs = config('countries.' . $template['country_code'] . '.languages', []);
        $langs = array_values(array_intersect($countryLangs, array_map('strval', (array) ($request->post['languages'] ?? []))));
        $fields = [
            'name' => trim($request->string('name')),
            'description' => trim($request->string('description')),
            'category' => $request->string('category'),
            'supported_languages' => implode(',', $langs),
            'is_recommended' => (bool) $request->input('is_recommended'),
            'sort_order' => max(0, min(9999, $request->int('sort_order', (int) $template['sort_order']))),
        ];
        $errors = [];
        if ($fields['name'] === '' || mb_strlen($fields['name']) > 120) {
            $errors['name'] = 'Use 1 to 120 characters.';
        }
        if (mb_strlen($fields['description']) > 600) {
            $errors['description'] = 'Use at most 600 characters.';
        }
        if (!in_array($fields['category'], self::CATEGORIES, true)) {
            $errors['category'] = 'Choose a category.';
        }
        if ($langs === []) {
            $errors['languages'] = 'Choose at least one language.';
        }
        if ($errors) {
            Session::flash('error', 'The template was not saved: ' . implode(' ', $errors));
            return $this->redirect('/admin/templates#t-' . $template['id']);
        }
        Template::updateMeta((int) $template['id'], $fields);
        AuditLog::record(Auth::id(), 'admin.template_updated', 'template', (int) $template['id'], ['slug' => $template['slug']] + $fields, $request->ip());
        Session::flash('success', "“{$fields['name']}” saved.");
        return $this->redirect('/admin/templates#t-' . $template['id']);
    }

    public function toggle(Request $request): Response
    {
        $template = Template::find((int) $request->param('id'));
        if (!$template) {
            throw new HttpException(404);
        }
        $active = !$template['is_active'];
        if (!$active) {
            $remaining = (int) \App\Core\Database::value(
                'SELECT COUNT(*) FROM templates WHERE country_code = ? AND is_active = 1 AND id <> ?',
                [$template['country_code'], $template['id']]
            );
            if ($remaining === 0) {
                Session::flash('error', 'Each country needs at least one active template.');
                return $this->redirect('/admin/templates');
            }
        }
        Template::setActive((int) $template['id'], $active);
        AuditLog::record(Auth::id(), $active ? 'admin.template_enabled' : 'admin.template_disabled', 'template', (int) $template['id'], ['slug' => $template['slug']], $request->ip());
        Session::flash('success', "“{$template['name']}” is now " . ($active ? 'available to users.' : 'hidden from new CVs. Existing CVs keep it.'));
        return $this->redirect('/admin/templates#t-' . $template['id']);
    }
}
