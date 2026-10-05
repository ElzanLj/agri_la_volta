<?php
/**
 * @var \App\Http\Admin\ListFilters $filters
 * @var list<array<string, mixed>> $rows
 * @var array{page: int, pages: int, total: int} $pagination
 * @var list<array<string, mixed>> $apartments
 */

use App\Http\Admin\Labels;

$action = '/admin/prenotazioni';
?>
<h1>Prenotazioni</h1>
<p><a class="button" href="<?= e(url('/admin/prenotazioni/nuova')) ?>">Nuova prenotazione manuale</a></p>

<?= \App\Http\View::capture('admin/_filters', ['action' => $action, 'filters' => $filters, 'statuses' => Labels::BOOKING_STATUSES, 'apartments' => $apartments, 'withOrigin' => true]) ?>

<div class="table-wrap">
<table>
    <caption class="visually-hidden">Elenco prenotazioni</caption>
    <thead>
        <tr>
            <th scope="col">N.</th>
            <th scope="col">Stato</th>
            <th scope="col">Origine</th>
            <th scope="col">Appartamento</th>
            <th scope="col">Arrivo</th>
            <th scope="col">Partenza</th>
            <th scope="col">Ospite</th>
            <th scope="col">Totale</th>
            <th scope="col"><span class="visually-hidden">Azioni</span></th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e((string) $r['id']) ?></td>
            <td><span class="badge badge-<?= e($r['status']) ?>"><?= e(Labels::bookingStatus($r['status'])) ?></span></td>
            <td><?= e(Labels::origin($r['origin'])) ?></td>
            <td><?= e($r['apartment_name']) ?></td>
            <td><?= e(Labels::date($r['check_in'])) ?></td>
            <td><?= e(Labels::date($r['check_out'])) ?></td>
            <td><?= e($r['guest_name']) ?></td>
            <td><?= e(Labels::money($r['total_cents'] === null ? null : (int) $r['total_cents'])) ?></td>
            <td><a href="<?= e(url('/admin/prenotazioni/' . $r['id'])) ?>">Apri</a></td>
        </tr>
<?php endforeach; ?>
<?php if ($rows === []): ?>
        <tr><td colspan="9">Nessuna prenotazione.</td></tr>
<?php endif; ?>
    </tbody>
</table>
</div>

<?= \App\Http\View::capture('admin/_pagination', ['pagination' => $pagination, 'filters' => $filters, 'action' => $action]) ?>
