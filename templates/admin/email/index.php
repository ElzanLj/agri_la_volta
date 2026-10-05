<?php
/**
 * @var \App\Http\Admin\ListFilters $filters
 * @var list<array<string, mixed>> $rows
 * @var array{page: int, pages: int, total: int} $pagination
 * @var array<string, int> $counts
 * @var string $transport
 */

use App\Http\Admin\Labels;

$action = '/admin/email';
?>
<h1>Email</h1>
<p>Le email vengono salvate in coda insieme alla modifica che le genera e inviate subito dopo. Se l'invio fallisce la richiesta o la prenotazione restano comunque salvate: qui puoi vedere cosa è successo e riprovare.</p>

<?php if ($transport === 'log'): ?>
<p class="alert alert-error" role="status">Modalità di sviluppo (<code>MAIL_TRANSPORT=log</code>): i messaggi vengono scritti in <code>storage/mail/</code> e non spediti.</p>
<?php endif; ?>

<p>
<?php foreach (Labels::EMAIL_STATUSES as $status => $label): ?>
    <a href="<?= e(url('/admin/email?stato=' . $status)) ?>"><?= e($label) ?>: <?= e((string) ($counts[$status] ?? 0)) ?></a><?= $status !== 'skipped' ? ' · ' : '' ?>
<?php endforeach; ?>
</p>

<?= \App\Http\View::capture('admin/_filters', ['action' => $action, 'filters' => $filters, 'statuses' => Labels::EMAIL_STATUSES, 'withPeriod' => false]) ?>

<div class="table-wrap">
<table>
    <caption class="visually-hidden">Coda email</caption>
    <thead>
        <tr>
            <th scope="col">N.</th><th scope="col">Creata</th><th scope="col">Messaggio</th><th scope="col">Collegata a</th>
            <th scope="col">Stato</th><th scope="col">Tentativi</th><th scope="col">Dettaglio</th><th scope="col"><span class="visually-hidden">Azioni</span></th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($rows as $m): ?>
        <tr>
            <td><?= e((string) $m['id']) ?></td>
            <td><?= e(Labels::dateTime($m['created_at'])) ?></td>
            <td><?= e(Labels::emailType($m['type'])) ?></td>
            <td>
<?php if ($m['booking_request_id'] !== null): ?>
                <a href="<?= e(url('/admin/richieste/' . $m['booking_request_id'])) ?>">richiesta <?= e((string) $m['booking_request_id']) ?></a>
<?php endif; ?>
<?php if ($m['booking_id'] !== null): ?>
                <a href="<?= e(url('/admin/prenotazioni/' . $m['booking_id'])) ?>">prenotazione <?= e((string) $m['booking_id']) ?></a>
<?php endif; ?>
            </td>
            <td><span class="badge badge-<?= e($m['status']) ?>"><?= e(Labels::emailStatus($m['status'])) ?></span></td>
            <td><?= e((string) $m['attempts']) ?></td>
            <td>
                <?= e((string) ($m['error_message'] ?? '')) ?>
<?php if ($m['next_attempt_at'] !== null && $m['status'] === 'failed'): ?>
                <br>Prossimo tentativo automatico: <?= e(Labels::dateTime($m['next_attempt_at'])) ?>
<?php endif; ?>
            </td>
            <td>
<?php if (in_array($m['status'], ['failed', 'pending', 'sending'], true)): ?>
                <form method="post" action="<?= e(url('/admin/email/' . $m['id'] . '/riprova')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button-secondary">Riprova invio</button>
                </form>
<?php endif; ?>
            </td>
        </tr>
<?php endforeach; ?>
<?php if ($rows === []): ?>
        <tr><td colspan="8">Nessuna email.</td></tr>
<?php endif; ?>
    </tbody>
</table>
</div>

<?= \App\Http\View::capture('admin/_pagination', ['pagination' => $pagination, 'filters' => $filters, 'action' => $action]) ?>
