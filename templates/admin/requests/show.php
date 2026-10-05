<?php
/**
 * @var array<string, mixed> $row
 * @var array<string, mixed>|null $booking
 * @var array{lines: list<array{label: string, detail: string, amount: string}>, total: ?string, note: ?string}|null $summary
 */

use App\Http\Admin\Labels;

$nights = (int) (new DateTimeImmutable((string) $row['check_in']))->diff(new DateTimeImmutable((string) $row['check_out']))->days;
?>
<h1>Richiesta <?= e($row['reference']) ?></h1>
<p><a href="<?= e(url('/admin/richieste')) ?>">← Tutte le richieste</a></p>

<dl class="details">
    <dt>Stato</dt>
    <dd><span class="badge badge-<?= e($row['status']) ?>"><?= e(Labels::requestStatus($row['status'])) ?></span></dd>
    <dt>Appartamento</dt>
    <dd><?= e($row['apartment_name']) ?></dd>
    <dt>Soggiorno</dt>
    <dd><?= e(Labels::date($row['check_in'])) ?> → <?= e(Labels::date($row['check_out'])) ?> (<?= e((string) $nights) ?> notti)</dd>
    <dt>Ospiti</dt>
    <dd><?= e((string) $row['adults']) ?> adulti, <?= e((string) $row['children']) ?> bambini, <?= e((string) $row['pets']) ?> animali</dd>
    <dt>Cliente</dt>
    <dd><?= e($row['first_name'] . ' ' . $row['last_name']) ?></dd>
    <dt>Email</dt>
    <dd><?= e($row['email']) ?></dd>
    <dt>Telefono</dt>
    <dd><?= e($row['phone']) ?></dd>
    <dt>Lingua</dt>
    <dd><?= e($row['locale']) ?></dd>
    <dt>Note del cliente</dt>
    <dd><?= $row['notes'] === null || $row['notes'] === '' ? '—' : nl2br(e($row['notes'])) ?></dd>
    <dt>Ricevuta il</dt>
    <dd><?= e(Labels::dateTime($row['created_at'])) ?> · consenso privacy: <?= e(Labels::dateTime($row['privacy_accepted_at'])) ?></dd>
    <dt>Decisa il</dt>
    <dd><?= e(Labels::dateTime($row['decided_at'])) ?></dd>
<?php if ($booking !== null): ?>
    <dt>Prenotazione</dt>
    <dd><a href="<?= e(url('/admin/prenotazioni/' . $booking['id'])) ?>">n. <?= e((string) $booking['id']) ?></a> (<?= e(Labels::bookingStatus($booking['status'])) ?>)</dd>
<?php endif; ?>
</dl>

<h2>Prezzo calcolato dal server</h2>
<?php if ($summary === null): ?>
<p>Nessun dettaglio disponibile.</p>
<?php else: ?>
<div class="table-wrap">
<table>
    <caption class="visually-hidden">Dettaglio del prezzo</caption>
    <thead><tr><th scope="col">Voce</th><th scope="col">Calcolo</th><th scope="col">Importo</th></tr></thead>
    <tbody>
<?php foreach ($summary['lines'] as $line): ?>
        <tr><td><?= e($line['label']) ?></td><td><?= e($line['detail']) ?></td><td><?= e($line['amount']) ?></td></tr>
<?php endforeach; ?>
<?php if ($summary['lines'] === []): ?>
        <tr><td colspan="3">Nessuna voce calcolata.</td></tr>
<?php endif; ?>
    </tbody>
    <tfoot>
        <tr><th scope="row" colspan="2">Totale</th><td><?= e($summary['total'] ?? 'da confermare') ?></td></tr>
    </tfoot>
</table>
</div>
<?php if ($summary['note'] !== null): ?>
<p>Il listino non copre tutte le notti: <strong><?= e($summary['note']) ?></strong>.</p>
<?php endif; ?>
<?php endif; ?>

<?php if ($row['status'] === 'pending'): ?>
<h2>Decisione</h2>
<p>Confermare crea la prenotazione e occupa le date; il sistema ricontrolla la disponibilità al momento della conferma. Per ora non vengono inviate email.</p>
<div class="actions">
    <form method="post" action="<?= e(url('/admin/richieste/' . $row['id'] . '/conferma')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="button">Conferma richiesta</button>
    </form>
    <form method="post" action="<?= e(url('/admin/richieste/' . $row['id'] . '/rifiuta')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="button button-secondary">Rifiuta richiesta</button>
    </form>
</div>
<?php endif; ?>
