<?php
/**
 * @var array<string, mixed> $booking
 * @var string $locale
 * @var string $subject
 * @var string $body
 * @var array<string, string> $errors
 * @var ?string $mailto
 * @var bool $hasEmail
 */
?>
<h1>Bozza di cancellazione — prenotazione n. <?= e((string) $booking['id']) ?></h1>
<p><a href="<?= e(url('/admin/prenotazioni/' . $booking['id'])) ?>">← Torna alla prenotazione</a></p>

<p>
    Questa è una <strong>bozza</strong>: non viene inviata automaticamente. Leggila, modificala e poi invia (oppure copia il testo).
    Lingua:
    <a href="<?= e(url('/admin/prenotazioni/' . $booking['id'] . '/bozza-cancellazione?lingua=it')) ?>">italiano</a> ·
    <a href="<?= e(url('/admin/prenotazioni/' . $booking['id'] . '/bozza-cancellazione?lingua=en')) ?>">inglese</a>
    (cambiare lingua ripristina il testo predefinito).
</p>
<p>
    Destinatario:
<?php if ($hasEmail): ?>
    <?= e((string) $booking['email']) ?>
<?php else: ?>
    <strong>nessun indirizzo email</strong> — copia il testo e invialo con un altro mezzo.
<?php endif; ?>
</p>

<?php if ($errors !== []): ?>
<p class="alert alert-error" role="alert">Correggi i campi evidenziati.</p>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/prenotazioni/' . $booking['id'] . '/bozza-cancellazione')) ?>" class="form-narrow">
    <?= csrf_field() ?>
    <input type="hidden" name="lingua" value="<?= e($locale) ?>">
    <div class="field">
        <label for="subject">Oggetto</label>
        <input type="text" id="subject" name="subject" maxlength="200" value="<?= e($subject) ?>" required<?= invalid_attrs($errors, 'subject', 'err-subject') ?>>
        <?= field_error($errors, 'subject', 'err-subject') ?>
    </div>
    <div class="field">
        <label for="body">Testo</label>
        <textarea id="body" name="body" rows="14" maxlength="10000" required<?= invalid_attrs($errors, 'body', 'err-body') ?>><?= e($body) ?></textarea>
        <?= field_error($errors, 'body', 'err-body') ?>
    </div>
<?php if ($hasEmail): ?>
    <button type="submit" class="button">Invia email al cliente</button>
<?php endif; ?>
</form>

<?php if ($mailto !== null): ?>
<p>In alternativa: <a href="<?= e($mailto) ?>">apri nel programma di posta</a> (con il testo predefinito).</p>
<?php endif; ?>
