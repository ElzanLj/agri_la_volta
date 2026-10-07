<?php
/**
 * Confirmation before rejecting a request. Nothing is written by this page.
 *
 * @var array<string, mixed> $row
 * @var array{to: string, subject: string, body: string}|null $preview
 * @var string|null $previewProblem
 */

use App\Http\Admin\Labels;

$nights = (int) (new DateTimeImmutable((string) $row['check_in']))->diff(new DateTimeImmutable((string) $row['check_out']))->days;
?>
<h1>Rifiuta la richiesta <?= e($row['reference']) ?></h1>
<p><a href="<?= e(url('/admin/richieste/' . $row['id'])) ?>">← Torna alla richiesta</a></p>

<p class="notice" role="note">Stai per rifiutare questa richiesta. Il cliente riceverà subito l'email qui sotto e la decisione non si può annullare.</p>

<h2>Riepilogo</h2>
<dl class="details">
    <dt>Cliente</dt>
    <dd><?= e($row['first_name'] . ' ' . $row['last_name']) ?></dd>
    <dt>Appartamento</dt>
    <dd><?= e($row['apartment_name']) ?></dd>
    <dt>Soggiorno</dt>
    <dd><?= e(Labels::date($row['check_in'])) ?> → <?= e(Labels::date($row['check_out'])) ?> (<?= e((string) $nights) ?> notti)</dd>
    <dt>Ospiti</dt>
    <dd><?= e((string) $row['adults']) ?> adulti, <?= e((string) $row['children']) ?> bambini, <?= e((string) $row['pets']) ?> animali</dd>
</dl>

<h2>Email che riceverà il cliente</h2>
<?php if ($preview === null): ?>
<p><?= e((string) $previewProblem) ?></p>
<?php else: ?>
<dl class="details">
    <dt>A</dt>
    <dd><?= e($preview['to']) ?></dd>
    <dt>Oggetto</dt>
    <dd><?= e($preview['subject']) ?></dd>
</dl>
<pre class="mail-preview"><?= e($preview['body']) ?></pre>
<?php endif; ?>

<div class="decision-reject">
    <form method="post" action="<?= e(url('/admin/richieste/' . $row['id'] . '/rifiuta')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="conferma" value="1">
        <button type="submit" class="button button-danger">Rifiuta e avvisa l'ospite</button>
    </form>
    <p><a class="button button-secondary" href="<?= e(url('/admin/richieste/' . $row['id'])) ?>">Torna indietro</a></p>
</div>
