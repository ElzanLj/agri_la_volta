<?php
/**
 * @var array<string, mixed> $row
 * @var string $reason
 */

use App\Http\Admin\Labels;
?>
<h1>Cancella la prenotazione n. <?= e((string) $row['id']) ?></h1>
<p><a href="<?= e(url('/admin/prenotazioni/' . $row['id'])) ?>">← Torna alla prenotazione</a></p>

<p>
    <?= e($row['guest_name']) ?> · <?= e($row['apartment_name']) ?> ·
    dal <?= e(Labels::date($row['check_in'])) ?> al <?= e(Labels::date($row['check_out'])) ?>
</p>
<p>Le date torneranno disponibili. L'email di cancellazione al cliente sarà una bozza modificabile e non viene inviata automaticamente (funzione non ancora disponibile).</p>

<form method="post" action="<?= e(url('/admin/prenotazioni/' . $row['id'] . '/cancella')) ?>" class="form-narrow">
    <?= csrf_field() ?>
    <div class="field">
        <label for="motivo">Motivo (facoltativo, solo uso interno)</label>
        <textarea id="motivo" name="motivo" rows="3" maxlength="1000"><?= e($reason) ?></textarea>
    </div>
    <button type="submit" class="button">Conferma la cancellazione</button>
    <a href="<?= e(url('/admin/prenotazioni/' . $row['id'])) ?>">Annulla</a>
</form>
