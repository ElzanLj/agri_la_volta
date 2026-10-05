<?php
/** @var array<string, mixed> $row */

use App\Http\Admin\Labels;

$nights = (int) (new DateTimeImmutable((string) $row['check_in']))->diff(new DateTimeImmutable((string) $row['check_out']))->days;
?>
<h1>Prenotazione n. <?= e((string) $row['id']) ?></h1>
<p><a href="<?= e(url('/admin/prenotazioni')) ?>">← Tutte le prenotazioni</a></p>

<dl class="details">
    <dt>Stato</dt>
    <dd><span class="badge badge-<?= e($row['status']) ?>"><?= e(Labels::bookingStatus($row['status'])) ?></span></dd>
    <dt>Origine</dt>
    <dd><?= e(Labels::origin($row['origin'])) ?><?php if ($row['request_reference'] !== null): ?> · richiesta <a href="<?= e(url('/admin/richieste/' . $row['booking_request_id'])) ?>"><?= e($row['request_reference']) ?></a><?php endif; ?></dd>
    <dt>Appartamento</dt>
    <dd><?= e($row['apartment_name']) ?></dd>
    <dt>Soggiorno</dt>
    <dd><?= e(Labels::date($row['check_in'])) ?> → <?= e(Labels::date($row['check_out'])) ?> (<?= e((string) $nights) ?> notti)</dd>
    <dt>Ospiti</dt>
    <dd><?= e((string) $row['adults']) ?> adulti, <?= e((string) $row['children']) ?> bambini, <?= e((string) $row['pets']) ?> animali</dd>
    <dt>Ospite</dt>
    <dd><?= e($row['guest_name']) ?></dd>
    <dt>Email</dt>
    <dd><?= e((string) ($row['email'] ?? '—')) ?></dd>
    <dt>Telefono</dt>
    <dd><?= e((string) ($row['phone'] ?? '—')) ?></dd>
    <dt>WhatsApp</dt>
    <dd><?php if ($whatsapp !== null): ?><a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer">Scrivi all'ospite su WhatsApp</a> (il testo è modificabile prima dell'invio)<?php else: ?>numero non utilizzabile per WhatsApp<?php endif; ?></dd>
    <dt>Totale</dt>
    <dd><?= e(Labels::money($row['total_cents'] === null ? null : (int) $row['total_cents'])) ?></dd>
    <dt>Note</dt>
    <dd><?= $row['notes'] === null || $row['notes'] === '' ? '—' : nl2br(e($row['notes'])) ?></dd>
    <dt>Creata il</dt>
    <dd><?= e(Labels::dateTime($row['created_at'])) ?></dd>
<?php if ($row['status'] === 'cancelled'): ?>
    <dt>Cancellata il</dt>
    <dd><?= e(Labels::dateTime($row['cancelled_at'])) ?></dd>
    <dt>Motivo</dt>
    <dd><?= $row['cancellation_reason'] === null ? '—' : nl2br(e($row['cancellation_reason'])) ?></dd>
<?php endif; ?>
</dl>

<?php if ($row['status'] === 'confirmed'): ?>
<p><a class="button button-secondary" href="<?= e(url('/admin/prenotazioni/' . $row['id'] . '/cancella')) ?>">Cancella prenotazione…</a></p>
<?php endif; ?>
<?php if ($row['status'] === 'cancelled'): ?>
<p><a class="button" href="<?= e(url('/admin/prenotazioni/' . $row['id'] . '/bozza-cancellazione')) ?>">Prepara l'email di cancellazione…</a> (non viene inviata automaticamente)</p>
<?php endif; ?>

<?= \App\Http\View::capture('admin/_email_status', ['emails' => $emails]) ?>
