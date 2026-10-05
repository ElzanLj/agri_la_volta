<?php
/**
 * Messages queued for a request or booking, with a retry button for the ones not delivered.
 *
 * @var list<array<string, mixed>> $emails
 */

use App\Http\Admin\Labels;
?>
<h2>Email</h2>
<?php if ($emails === []): ?>
<p>Nessuna email collegata.</p>
<?php else: ?>
<div class="table-wrap">
<table>
    <caption class="visually-hidden">Stato delle email</caption>
    <thead>
        <tr><th scope="col">Messaggio</th><th scope="col">Stato</th><th scope="col">Tentativi</th><th scope="col">Dettaglio</th><th scope="col"><span class="visually-hidden">Azioni</span></th></tr>
    </thead>
    <tbody>
<?php foreach ($emails as $m): ?>
        <tr>
            <td><?= e(Labels::emailType($m['type'])) ?></td>
            <td><span class="badge badge-<?= e($m['status']) ?>"><?= e(Labels::emailStatus($m['status'])) ?></span></td>
            <td><?= e((string) $m['attempts']) ?></td>
            <td><?= e((string) ($m['error_message'] ?? '')) ?><?= $m['sent_at'] !== null ? e('Inviata il ' . Labels::dateTime($m['sent_at'])) : '' ?></td>
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
    </tbody>
</table>
</div>
<?php endif; ?>
