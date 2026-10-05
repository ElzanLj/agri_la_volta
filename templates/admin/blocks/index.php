<?php
/**
 * @var list<array<string, mixed>> $rows
 * @var list<array<string, mixed>> $apartments
 * @var array<string, string> $values
 * @var array<string, string> $errors
 * @var ?string $notice
 */

use App\Http\Admin\Labels;
?>
<h1>Blocchi di disponibilità</h1>
<p>Un blocco chiude alcune date di un appartamento (manutenzione, uso privato…). Non si può creare su date già occupate da una prenotazione confermata.</p>

<?php if ($notice !== null): ?>
<p class="alert alert-error" role="alert"><?= e($notice) ?></p>
<?php endif; ?>

<h2>Nuovo blocco</h2>
<form method="post" action="<?= e(url('/admin/blocchi')) ?>" class="form-narrow">
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
        <label for="start_date">Dal (primo giorno chiuso)</label>
        <input type="date" id="start_date" name="start_date" value="<?= e(old($values, 'start_date')) ?>" required<?= invalid_attrs($errors, 'start_date', 'err-start_date') ?>>
        <?= field_error($errors, 'start_date', 'err-start_date') ?>
    </div>
    <div class="field">
        <label for="end_date">Al (giorno in cui si riapre, escluso)</label>
        <input type="date" id="end_date" name="end_date" value="<?= e(old($values, 'end_date')) ?>" required<?= invalid_attrs($errors, 'end_date', 'err-end_date') ?>>
        <?= field_error($errors, 'end_date', 'err-end_date') ?>
    </div>
    <div class="field">
        <label for="reason">Motivo (facoltativo)</label>
        <input type="text" id="reason" name="reason" maxlength="255" value="<?= e(old($values, 'reason')) ?>"<?= invalid_attrs($errors, 'reason', 'err-reason') ?>>
        <?= field_error($errors, 'reason', 'err-reason') ?>
    </div>
    <button type="submit" class="button">Crea blocco</button>
</form>

<h2>Blocchi esistenti</h2>
<div class="table-wrap">
<table>
    <caption class="visually-hidden">Blocchi di disponibilità</caption>
    <thead>
        <tr><th scope="col">N.</th><th scope="col">Appartamento</th><th scope="col">Dal</th><th scope="col">Al (escluso)</th><th scope="col">Motivo</th><th scope="col"><span class="visually-hidden">Azioni</span></th></tr>
    </thead>
    <tbody>
<?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e((string) $r['id']) ?></td>
            <td><?= e($r['apartment_name']) ?></td>
            <td><?= e(Labels::date($r['start_date'])) ?></td>
            <td><?= e(Labels::date($r['end_date'])) ?></td>
            <td><?= e((string) ($r['reason'] ?? '')) ?></td>
            <td>
                <form method="post" action="<?= e(url('/admin/blocchi/' . $r['id'] . '/rimuovi')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button-secondary">Rimuovi</button>
                </form>
            </td>
        </tr>
<?php endforeach; ?>
<?php if ($rows === []): ?>
        <tr><td colspan="6">Nessun blocco.</td></tr>
<?php endif; ?>
    </tbody>
</table>
</div>
