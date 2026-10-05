<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Admin\Labels;
use App\Http\Admin\ListFilters;
use App\Http\Request;
use App\Http\Response;

/** Read-only change history (SPEC §15). */
final class AuditController extends BasePage
{
    public function index(Request $request): Response
    {
        $filters = ListFilters::fromRequest($request, allowedEntities: Labels::ENTITIES);
        $valid = $filters->isValid();
        $queries = $this->queries();

        return $this->render('admin/audit/index', [
            'title' => 'Storico modifiche',
            'filters' => $filters,
            'rows' => $valid ? $queries->audit($filters) : [],
            'pagination' => $this->pagination($filters->page, $valid ? $queries->countAudit($filters) : 0, ListFilters::PER_PAGE),
        ], $valid ? 200 : 400);
    }
}
