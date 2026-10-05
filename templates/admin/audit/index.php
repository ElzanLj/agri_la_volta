<?php
/**
 * @var \App\Http\Admin\ListFilters $filters
 * @var list<array<string, mixed>> $rows
 * @var array{page: int, pages: int, total: int} $pagination
 */

use App\Http\Admin\Labels;

$action = '/admin/storico';
$pretty = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '—';
    }
    $data = json_decode($json, true);
    return is_array($data) ? (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $json;
};
?>
<h1>Storico delle modifiche</h1>
<p>Cosa è cambiato nel sistema, con data e ora. Sola lettura.</p>

<?= \App\Http\View::capture('admin/_filters', ['action' => $action, 'filters' => $filters, 'entities' => Labels::ENTITIES, 'withPeriod' => false]) ?>

<div class="table-wrap">
<table>
    <caption class="visually-hidden">Storico modifiche</caption>
    <thead>
        <tr><th scope="col">Quando</th><th scope="col">Tipo</th><th scope="col">N.</th><th scope="col">Azione</th><th scope="col">Riepilogo</th><th scope="col">Valori</th></tr>
    </thead>
    <tbody>
<?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e(Labels::dateTime($r['created_at'])) ?></td>
            <td><?= e(Labels::entity($r['entity_type'])) ?></td>
            <td><?= e((string) ($r['entity_id'] ?? '')) ?></td>
            <td><?= e($r['action']) ?></td>
            <td><?= e((string) ($r['summary'] ?? '')) ?></td>
            <td>
<?php if ($r['old_values'] !== null || $r['new_values'] !== null): ?>
                <details>
                    <summary>Mostra</summary>
                    <p>Prima:</p><pre><?= e($pretty($r['old_values'])) ?></pre>
                    <p>Dopo:</p><pre><?= e($pretty($r['new_values'])) ?></pre>
                </details>
<?php endif; ?>
            </td>
        </tr>
<?php endforeach; ?>
<?php if ($rows === []): ?>
        <tr><td colspan="6">Nessuna voce.</td></tr>
<?php endif; ?>
    </tbody>
</table>
</div>

<?= \App\Http\View::capture('admin/_pagination', ['pagination' => $pagination, 'filters' => $filters, 'action' => $action]) ?>
