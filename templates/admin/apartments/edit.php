<?php
/**
 * @var array<string, mixed> $row
 * @var array<string, string> $values
 * @var array<string, string> $errors
 * @var ?string $notice
 */

/** Renders a labelled text/number input with its error message. */
$input = static function (string $field, string $label, string $type = 'text', string $extra = '') use ($values, $errors): string {
    $id = 'f-' . $field;
    return '<div class="field"><label for="' . e($id) . '">' . e($label) . '</label>'
        . '<input type="' . e($type) . '" id="' . e($id) . '" name="' . e($field) . '" value="' . e(old($values, $field)) . '" ' . $extra
        . invalid_attrs($errors, $field, 'err-' . $field) . '>'
        . field_error($errors, $field, 'err-' . $field) . '</div>';
};
$area = static function (string $field, string $label, int $max) use ($values, $errors): string {
    $id = 'f-' . $field;
    return '<div class="field"><label for="' . e($id) . '">' . e($label) . '</label>'
        . '<textarea id="' . e($id) . '" name="' . e($field) . '" rows="4" maxlength="' . $max . '"' . invalid_attrs($errors, $field, 'err-' . $field) . '>'
        . e(old($values, $field)) . '</textarea>' . field_error($errors, $field, 'err-' . $field) . '</div>';
};
?>
<h1>Appartamento <?= e($row['name']) ?></h1>
<p><a href="<?= e(url('/admin/appartamenti')) ?>">← Tutti gli appartamenti</a></p>
<p>Indirizzo web (non modificabile, per non rompere i link): <code><?= e($row['slug']) ?></code>. Foto e dotazioni verranno gestite in una fase successiva.</p>

<?php if ($notice !== null): ?>
<p class="alert alert-error" role="alert"><?= e($notice) ?></p>
<?php endif; ?>
<?php if ($errors !== []): ?>
<p class="alert alert-error" role="alert">Controlla i campi evidenziati.</p>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/appartamenti/' . $row['id'])) ?>" class="form-narrow">
    <?= csrf_field() ?>

    <?= $input('name', 'Nome', 'text', 'maxlength="100" required') ?>
    <?= $input('sort_order', 'Ordine di visualizzazione', 'text', 'inputmode="numeric"') ?>

    <div class="field">
        <label><input type="checkbox" name="is_active" value="1"<?= old($values, 'is_active') === '1' ? ' checked' : '' ?>> Appartamento attivo</label>
    </div>
    <div class="field">
        <label><input type="checkbox" name="accepts_online_requests" value="1"<?= old($values, 'accepts_online_requests') === '1' ? ' checked' : '' ?>> Accetta richieste dal sito</label>
    </div>

    <div class="field">
        <label for="f-management_mode">Gestione</label>
        <select id="f-management_mode" name="management_mode"<?= invalid_attrs($errors, 'management_mode', 'err-management_mode') ?>>
            <option value="direct"<?= old($values, 'management_mode', 'direct') === 'direct' ? ' selected' : '' ?>>Diretta</option>
            <option value="agency"<?= old($values, 'management_mode') === 'agency' ? ' selected' : '' ?>>Temporaneamente tramite agenzia</option>
        </select>
        <?= field_error($errors, 'management_mode', 'err-management_mode') ?>
    </div>
    <?= $input('managing_agency', 'Agenzia (solo se gestito da agenzia)', 'text', 'maxlength="100"') ?>

    <h2>Capienza e dotazioni</h2>
    <p>Lasciare vuoto = non configurato (nessun limite applicato).</p>
    <?= $input('max_guests', 'Ospiti massimi (adulti + bambini)', 'number', 'min="1" max="50"') ?>
    <?= $input('max_children', 'Bambini massimi', 'number', 'min="0" max="20"') ?>
    <?= $input('max_pets', 'Animali massimi (0 = non ammessi)', 'number', 'min="0" max="20"') ?>
    <?= $input('bedrooms', 'Camere', 'number', 'min="0" max="30"') ?>
    <?= $input('beds', 'Letti', 'number', 'min="0" max="60"') ?>

    <h2>Orari e prezzo indicativo</h2>
    <?= $input('check_in_from', 'Arrivo dalle (HH:MM)', 'time') ?>
    <?= $input('check_in_until', 'Arrivo fino alle (HH:MM)', 'time') ?>
    <?= $input('check_out_until', 'Partenza entro le (HH:MM)', 'time') ?>
    <?= $input('indicative_price', 'Prezzo indicativo a notte in euro (solo visualizzazione, non usato nei calcoli)', 'text', 'inputmode="decimal"') ?>

<?php foreach (['it' => 'Italiano', 'en' => 'Inglese'] as $locale => $name): ?>
    <h2>Testi in <?= e(strtolower($name)) ?></h2>
    <?= $area("description_{$locale}", "Descrizione ({$name})", 5000) ?>
    <?= $area("rules_{$locale}", "Regole ({$name})", 5000) ?>
    <?= $input("meta_title_{$locale}", "Titolo per i motori di ricerca ({$name})", 'text', 'maxlength="255"') ?>
    <?= $input("meta_description_{$locale}", "Descrizione per i motori di ricerca ({$name})", 'text', 'maxlength="300"') ?>
<?php endforeach; ?>

    <button type="submit" class="button">Salva</button>
</form>
