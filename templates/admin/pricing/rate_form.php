<?php
/**
 * @var ?int $id
 * @var array<string, string> $values
 * @var array<string, string> $errors
 * @var ?string $notice
 * @var list<array<string, mixed>> $apartments
 */

$action = $id === null ? '/admin/listino/tariffe' : '/admin/listino/tariffe/' . $id;
$apartmentId = old($values, 'apartment_id');
?>
<h1><?= $id === null ? 'Nuova tariffa' : 'Modifica tariffa' ?></h1>
<p><a href="<?= e(url('/admin/listino?appartamento=' . urlencode($apartmentId))) ?>">← Listino</a></p>

<?php if ($notice !== null): ?>
<p class="alert alert-error" role="alert"><?= e($notice) ?></p>
<?php endif; ?>
<?php if ($errors !== []): ?>
<p class="alert alert-error" role="alert">Controlla i campi evidenziati.</p>
<?php endif; ?>

<form method="post" action="<?= e(url($action)) ?>" class="form-narrow">
    <?= csrf_field() ?>

<?php if ($id === null): ?>
    <div class="field">
        <label for="apartment_id">Appartamento</label>
        <select id="apartment_id" name="apartment_id" required<?= invalid_attrs($errors, 'apartment_id', 'err-apartment_id') ?>>
<?php foreach ($apartments as $a): ?>
            <option value="<?= e((string) $a['id']) ?>"<?= $apartmentId === (string) $a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option>
<?php endforeach; ?>
        </select>
        <?= field_error($errors, 'apartment_id', 'err-apartment_id') ?>
    </div>
<?php else: ?>
    <input type="hidden" name="apartment_id" value="<?= e($apartmentId) ?>">
<?php endif; ?>

    <div class="field">
        <label for="label_it">Nome del periodo (italiano)</label>
        <input type="text" id="label_it" name="label_it" maxlength="100" value="<?= e(old($values, 'label_it')) ?>" required<?= invalid_attrs($errors, 'label_it', 'err-label_it') ?>>
        <?= field_error($errors, 'label_it', 'err-label_it') ?>
    </div>
    <div class="field">
        <label for="label_en">Nome del periodo (inglese, facoltativo)</label>
        <input type="text" id="label_en" name="label_en" maxlength="100" value="<?= e(old($values, 'label_en')) ?>"<?= invalid_attrs($errors, 'label_en', 'err-label_en') ?>>
        <?= field_error($errors, 'label_en', 'err-label_en') ?>
    </div>
    <div class="field">
        <label for="start_date">Dal (primo giorno)</label>
        <input type="date" id="start_date" name="start_date" value="<?= e(old($values, 'start_date')) ?>" required<?= invalid_attrs($errors, 'start_date', 'err-start_date') ?>>
        <?= field_error($errors, 'start_date', 'err-start_date') ?>
    </div>
    <div class="field">
        <label for="end_date">Al (primo giorno escluso)</label>
        <input type="date" id="end_date" name="end_date" value="<?= e(old($values, 'end_date')) ?>" required<?= invalid_attrs($errors, 'end_date', 'err-end_date') ?>>
        <?= field_error($errors, 'end_date', 'err-end_date') ?>
    </div>
    <div class="field">
        <label for="nightly_rate">Prezzo a notte in euro (può essere 0 se si prezza solo a persona)</label>
        <input type="text" id="nightly_rate" name="nightly_rate" inputmode="decimal" value="<?= e(old($values, 'nightly_rate')) ?>" required<?= invalid_attrs($errors, 'nightly_rate', 'err-nightly_rate') ?>>
        <?= field_error($errors, 'nightly_rate', 'err-nightly_rate') ?>
    </div>
    <div class="field">
        <label for="min_nights">Soggiorno minimo in notti (facoltativo; vale per chi arriva in questo periodo)</label>
        <input type="number" id="min_nights" name="min_nights" min="1" max="60" value="<?= e(old($values, 'min_nights')) ?>"<?= invalid_attrs($errors, 'min_nights', 'err-min_nights') ?>>
        <?= field_error($errors, 'min_nights', 'err-min_nights') ?>
    </div>
    <div class="field">
        <label><input type="checkbox" name="is_active" value="1"<?= old($values, 'is_active') === '1' ? ' checked' : '' ?>> Tariffa attiva</label>
    </div>

    <button type="submit" class="button">Salva</button>
</form>
