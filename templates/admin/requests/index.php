<?php
/**
 * @var \App\Http\Admin\ListFilters $filters
 * @var list<array<string, mixed>> $rows
 * @var array{page: int, pages: int, total: int} $pagination
 * @var list<array<string, mixed>> $apartments
 */

use App\Http\Admin\Labels;

$action = '/admin/richieste';
?>
<h1>Richieste</h1>

<?= \App\Http\View::capture('admin/_filters', ['action' => $action, 'filters' => $filters, 'statuses' => Labels::REQUEST_STATUSES, 'apartments' => $apartments]) ?>

<div class="table-wrap">
<table>
    <caption class="visually-hidden">Elenco richieste di soggiorno</caption>
    <thead>
        <tr>
            <th scope="col">Ricevuta</th>
            <th scope="col">Riferimento</th>
            <th scope="col">Stato</th>
            <th scope="col">Appartamento</th>
            <th scope="col">Arrivo</th>
            <th scope="col">Partenza</th>
            <th scope="col">Ospiti</th>
            <th scope="col">Cliente</th>
            <th scope="col">Totale</th>
            <th scope="col"><span class="visually-hidden">Azioni</span></th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e(Labels::dateTime($r['created_at'])) ?></td>
            <td><?= e($r['reference']) ?></td>
            <td><span class="badge badge-<?= e($r['status']) ?>"><?= e(Labels::requestStatus($r['status'])) ?></span></td>
            <td><?= e($r['apartment_name']) ?></td>
            <td><?= e(Labels::date($r['check_in'])) ?></td>
            <td><?= e(Labels::date($r['check_out'])) ?></td>
            <td><?= e((string) $r['adults']) ?> ad. · <?= e((string) $r['children']) ?> bamb. · <?= e((string) $r['pets']) ?> anim.</td>
            <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
            <td><?= e($r['quoted_total_cents'] === null ? 'da confermare' : Labels::money((int) $r['quoted_total_cents'])) ?></td>
            <td><a href="<?= e(url('/admin/richieste/' . $r['id'])) ?>">Apri</a></td>
        </tr>
<?php endforeach; ?>
<?php if ($rows === []): ?>
        <tr><td colspan="10">Nessuna richiesta.</td></tr>
<?php endif; ?>
    </tbody>
</table>
</div>

<?= \App\Http\View::capture('admin/_pagination', ['pagination' => $pagination, 'filters' => $filters, 'action' => $action]) ?>
