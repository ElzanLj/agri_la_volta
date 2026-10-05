<?php
/**
 * @var ?int $id
 * @var array<string, string> $values
 * @var array<string, string> $errors
 * @var ?string $notice
 * @var list<array<string, mixed>> $apartments
 */

use App\Http\Admin\Labels;

$action = $id === null ? '/admin/listino/regole' : '/admin/listino/regole/' . $id;
$apartmentId = old($values, 'apartment_id');
$back = '/admin/listino' . ($apartmentId !== '' ? '?appartamento=' . urlencode($apartmentId) : '');
?>
<h1><?= $id === null ? 'Nuova regola' : 'Modifica regola' ?></h1>
<p><a href="<?= e(url($back)) ?>">← Listino</a></p>
<p>Esempi: «adulto oltre i 2 inclusi, per notte»; «bambino oltre il primo gratuito, per notte»; «animale, per soggiorno»; «pulizia finale» (supplemento fisso, per soggiorno).</p>

<?php if ($notice !== null): ?>
<p class="alert alert-error" role="alert"><?= e($notice) ?></p>
<?php endif; ?>
<?php if ($errors !== []): ?>
<p class="alert alert-error" role="alert">Controlla i campi evidenziati.</p>
<?php endif; ?>

<form method="post" action="<?= e(url($action)) ?>" class="form-narrow">
    <?= csrf_field() ?>

    <div class="field">
        <label for="apartment_id">Appartamento</label>
        <select id="apartment_id" name="apartment_id"<?= invalid_attrs($errors, 'apartment_id', 'err-apartment_id') ?>>
            <option value="">Tutti gli appartamenti</option>
<?php foreach ($apartments as $a): ?>
            <option value="<?= e((string) $a['id']) ?>"<?= $apartmentId === (string) $a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option>
<?php endforeach; ?>
        </select>
        <?= field_error($errors, 'apartment_id', 'err-apartment_id') ?>
    </div>

    <div class="field">
        <label for="applies_to">Voce</label>
        <select id="applies_to" name="applies_to" required<?= invalid_attrs($errors, 'applies_to', 'err-applies_to') ?>>
<?php foreach (Labels::APPLIES_TO as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= old($values, 'applies_to') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
        <?= field_error($errors, 'applies_to', 'err-applies_to') ?>
    </div>
    <div class="field">
        <label for="charge_basis">Si paga</label>
        <select id="charge_basis" name="charge_basis" required<?= invalid_attrs($errors, 'charge_basis', 'err-charge_basis') ?>>
<?php foreach (Labels::BASES as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= old($values, 'charge_basis') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
        <?= field_error($errors, 'charge_basis', 'err-charge_basis') ?>
    </div>
    <div class="field">
        <label for="amount">Importo in euro (per ogni unità addebitata)</label>
        <input type="text" id="amount" name="amount" inputmode="decimal" value="<?= e(old($values, 'amount')) ?>" required<?= invalid_attrs($errors, 'amount', 'err-amount') ?>>
        <?= field_error($errors, 'amount', 'err-amount') ?>
    </div>
    <div class="field">
        <label for="free_units">Quantità gratuite (es. adulti già inclusi nel prezzo base); 0 per i supplementi fissi</label>
        <input type="number" id="free_units" name="free_units" min="0" max="20" value="<?= e(old($values, 'free_units', '0')) ?>"<?= invalid_attrs($errors, 'free_units', 'err-free_units') ?>>
        <?= field_error($errors, 'free_units', 'err-free_units') ?>
    </div>
    <div class="field">
        <label for="valid_from">Valida dal (facoltativo)</label>
        <input type="date" id="valid_from" name="valid_from" value="<?= e(old($values, 'valid_from')) ?>"<?= invalid_attrs($errors, 'valid_from', 'err-valid_from') ?>>
        <?= field_error($errors, 'valid_from', 'err-valid_from') ?>
    </div>
    <div class="field">
        <label for="valid_to">Valida fino al (escluso, facoltativo)</label>
        <input type="date" id="valid_to" name="valid_to" value="<?= e(old($values, 'valid_to')) ?>"<?= invalid_attrs($errors, 'valid_to', 'err-valid_to') ?>>
        <?= field_error($errors, 'valid_to', 'err-valid_to') ?>
    </div>
    <div class="field">
        <label for="label_it">Nome della voce (italiano)</label>
        <input type="text" id="label_it" name="label_it" maxlength="100" value="<?= e(old($values, 'label_it')) ?>" required<?= invalid_attrs($errors, 'label_it', 'err-label_it') ?>>
        <?= field_error($errors, 'label_it', 'err-label_it') ?>
    </div>
    <div class="field">
        <label for="label_en">Nome della voce (inglese, facoltativo)</label>
        <input type="text" id="label_en" name="label_en" maxlength="100" value="<?= e(old($values, 'label_en')) ?>"<?= invalid_attrs($errors, 'label_en', 'err-label_en') ?>>
        <?= field_error($errors, 'label_en', 'err-label_en') ?>
    </div>
    <div class="field">
        <label for="sort_order">Ordine nel riepilogo</label>
        <input type="text" id="sort_order" name="sort_order" inputmode="numeric" value="<?= e(old($values, 'sort_order', '0')) ?>"<?= invalid_attrs($errors, 'sort_order', 'err-sort_order') ?>>
        <?= field_error($errors, 'sort_order', 'err-sort_order') ?>
    </div>
    <div class="field">
        <label><input type="checkbox" name="is_active" value="1"<?= old($values, 'is_active') === '1' ? ' checked' : '' ?>> Regola attiva</label>
    </div>

    <button type="submit" class="button">Salva</button>
</form>
