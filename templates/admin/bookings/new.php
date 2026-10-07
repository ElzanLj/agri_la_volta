<?php
/**
 * @var array<string, string> $values
 * @var array<string, string> $errors field => message
 * @var ?string $notice
 * @var list<array<string, mixed>> $apartments
 */

use App\Http\Admin\Labels;

$origins = array_diff_key(Labels::ORIGINS, ['website' => true]); // "website" is reserved for approved requests
?>
<h1>Nuova prenotazione manuale</h1>
<p><a href="<?= e(url('/admin/prenotazioni')) ?>">← Tutte le prenotazioni</a></p>
<p>Per prenotazioni ricevute da telefono, email, agenzie o altri canali. Occupa subito le date.</p>

<?php if ($notice !== null): ?>
<p class="alert alert-error" role="alert"><?= e($notice) ?></p>
<?php endif; ?>
<?php if ($errors !== []): ?>
<p class="alert alert-error" role="alert">Controlla i campi evidenziati.</p>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/prenotazioni')) ?>" class="form-narrow">
    <?= csrf_field() ?>

    <div class="field">
        <label for="apartment_id">Appartamento</label>
        <select id="apartment_id" name="apartment_id" required<?= invalid_attrs($errors, 'apartment_id', 'err-apartment_id') ?>>
            <option value="">Scegli…</option>
<?php foreach ($apartments as $a): ?>
            <option value="<?= e((string) $a['id']) ?>"<?= old($values, 'apartment_id') === (string) $a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option>
<?php endforeach; ?>
        </select>
        <?= field_error($errors, 'apartment_id', 'err-apartment_id') ?>
    </div>

    <div class="field">
        <label for="origin">Origine</label>
        <select id="origin" name="origin" required<?= invalid_attrs($errors, 'origin', 'err-origin') ?>>
            <option value="">Scegli…</option>
<?php foreach ($origins as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= old($values, 'origin') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
        <?= field_error($errors, 'origin', 'err-origin') ?>
    </div>

    <div class="field">
        <label for="check_in">Arrivo</label>
        <input type="date" id="check_in" name="check_in" value="<?= e(old($values, 'check_in')) ?>" required<?= invalid_attrs($errors, 'check_in', 'err-check_in') ?>>
        <?= field_error($errors, 'check_in', 'err-check_in') ?>
    </div>
    <div class="field">
        <label for="check_out">Partenza</label>
        <input type="date" id="check_out" name="check_out" value="<?= e(old($values, 'check_out')) ?>" required<?= invalid_attrs($errors, 'check_out', 'err-check_out') ?>>
        <?= field_error($errors, 'check_out', 'err-check_out') ?>
    </div>

    <div class="field">
        <label for="adults">Adulti</label>
        <input type="number" id="adults" name="adults" min="1" max="20" value="<?= e(old($values, 'adults', '2')) ?>" required<?= invalid_attrs($errors, 'adults', 'err-adults') ?>>
        <?= field_error($errors, 'adults', 'err-adults') ?>
        <?= field_error($errors, 'guests', 'err-guests') ?>
    </div>
    <div class="field">
        <label for="children">Bambini</label>
        <input type="number" id="children" name="children" min="0" max="20" value="<?= e(old($values, 'children', '0')) ?>"<?= invalid_attrs($errors, 'children', 'err-children') ?>>
        <?= field_error($errors, 'children', 'err-children') ?>
    </div>
    <div class="field">
        <label for="pets">Animali</label>
        <input type="number" id="pets" name="pets" min="0" max="10" value="<?= e(old($values, 'pets', '0')) ?>"<?= invalid_attrs($errors, 'pets', 'err-pets') ?>>
        <?= field_error($errors, 'pets', 'err-pets') ?>
    </div>

    <div class="field">
        <label for="guest_name">Nome dell'ospite</label>
        <input type="text" id="guest_name" name="guest_name" maxlength="200" value="<?= e(old($values, 'guest_name')) ?>" required<?= invalid_attrs($errors, 'guest_name', 'err-guest_name') ?>>
        <?= field_error($errors, 'guest_name', 'err-guest_name') ?>
    </div>
    <div class="field">
        <label for="email">Email (facoltativa)</label>
        <input type="email" id="email" name="email" maxlength="254" value="<?= e(old($values, 'email')) ?>"<?= invalid_attrs($errors, 'email', 'err-email') ?>>
        <?= field_error($errors, 'email', 'err-email') ?>
    </div>
    <div class="field">
        <label for="phone">Telefono (facoltativo)</label>
        <input type="text" id="phone" name="phone" maxlength="40" value="<?= e(old($values, 'phone')) ?>"<?= invalid_attrs($errors, 'phone', 'err-phone') ?>>
        <?= field_error($errors, 'phone', 'err-phone') ?>
    </div>
    <div class="field">
        <label for="total">Totale in euro (facoltativo)</label>
        <input type="text" id="total" name="total" inputmode="decimal" value="<?= e(old($values, 'total')) ?>"<?= invalid_attrs($errors, 'total', 'err-total') ?>>
        <?= field_error($errors, 'total', 'err-total') ?>
    </div>
    <div class="field">
        <label for="notes">Note</label>
        <textarea id="notes" name="notes" rows="3" maxlength="2000"<?= invalid_attrs($errors, 'notes', 'err-notes') ?>><?= e(old($values, 'notes')) ?></textarea>
        <p class="hint" id="notes-hint">Scrivi solo ciò che serve. Niente dati sanitari né dati di altre persone.</p>
        <?= field_error($errors, 'notes', 'err-notes') ?>
    </div>

    <button type="submit" class="button">Registra prenotazione</button>
</form>
