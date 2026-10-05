<?php
/**
 * @var list<array<string, mixed>> $apartments
 * @var array<string, mixed> $selected
 * @var list<array<string, mixed>> $rates
 * @var list<array<string, mixed>> $rules
 * @var list<array{start_date: string, end_date: string}> $gaps
 * @var array<int, string> $apartmentNames
 */

use App\Http\Admin\Labels;

$sid = (string) $selected['id'];
?>
<h1>Listino prezzi</h1>
<p>Prezzo base per appartamento e per notte, più voci aggiuntive (adulti, bambini, animali, supplementi). Importi in euro. Nessun prezzo è preimpostato.</p>

<form method="get" action="<?= e(url('/admin/listino')) ?>" class="filters">
    <div class="field">
        <label for="f-appartamento">Appartamento</label>
        <select id="f-appartamento" name="appartamento">
<?php foreach ($apartments as $a): ?>
            <option value="<?= e((string) $a['id']) ?>"<?= (string) $a['id'] === $sid ? ' selected' : '' ?>><?= e($a['name']) ?></option>
<?php endforeach; ?>
        </select>
    </div>
    <div class="field"><button type="submit" class="button">Mostra</button></div>
</form>

<h2>Tariffe per periodo — <?= e($selected['name']) ?></h2>
<p><a class="button" href="<?= e(url('/admin/listino/tariffe/nuova?appartamento=' . $sid)) ?>">Nuova tariffa</a></p>
<div class="table-wrap">
<table>
    <caption class="visually-hidden">Tariffe di <?= e($selected['name']) ?></caption>
    <thead>
        <tr><th scope="col">Periodo</th><th scope="col">Dal</th><th scope="col">Al (escluso)</th><th scope="col">A notte</th><th scope="col">Soggiorno minimo</th><th scope="col">Attiva</th><th scope="col"><span class="visually-hidden">Azioni</span></th></tr>
    </thead>
    <tbody>
<?php foreach ($rates as $r): ?>
        <tr>
            <td><?= e($r['label']) ?></td>
            <td><?= e(Labels::date($r['start_date'])) ?></td>
            <td><?= e(Labels::date($r['end_date'])) ?></td>
            <td><?= e(Labels::money((int) $r['nightly_rate_cents'])) ?></td>
            <td><?= e($r['min_nights'] === null ? '—' : (string) $r['min_nights'] . ' notti') ?></td>
            <td><?= (int) $r['is_active'] === 1 ? 'sì' : 'no' ?></td>
            <td class="row-actions">
                <a href="<?= e(url('/admin/listino/tariffe/' . $r['id'])) ?>">Modifica</a>
                <form method="post" action="<?= e(url('/admin/listino/tariffe/' . $r['id'] . '/elimina')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button-secondary">Elimina</button>
                </form>
            </td>
        </tr>
<?php endforeach; ?>
<?php if ($rates === []): ?>
        <tr><td colspan="7">Nessuna tariffa: le richieste per questo appartamento restano «prezzo da confermare».</td></tr>
<?php endif; ?>
    </tbody>
</table>
</div>

<h3>Date senza tariffa (prossimi 12 mesi)</h3>
<?php if ($gaps === []): ?>
<p>Tutte le notti sono coperte da una tariffa attiva.</p>
<?php else: ?>
<ul>
<?php foreach ($gaps as $g): ?>
    <li>dal <?= e(Labels::date($g['start_date'])) ?> al <?= e(Labels::date($g['end_date'])) ?> (escluso)</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<h2>Regole aggiuntive</h2>
<p>Valgono per <?= e($selected['name']) ?> e quelle impostate per «tutti gli appartamenti».</p>
<p><a class="button" href="<?= e(url('/admin/listino/regole/nuova?appartamento=' . $sid)) ?>">Nuova regola</a></p>
<div class="table-wrap">
<table>
    <caption class="visually-hidden">Regole di prezzo</caption>
    <thead>
        <tr><th scope="col">Voce</th><th scope="col">Per</th><th scope="col">Importo</th><th scope="col">Gratuiti</th><th scope="col">Validità</th><th scope="col">Appartamento</th><th scope="col">Attiva</th><th scope="col"><span class="visually-hidden">Azioni</span></th></tr>
    </thead>
    <tbody>
<?php foreach ($rules as $r): ?>
        <tr>
            <td><?= e($r['label_it']) ?></td>
            <td><?= e(Labels::APPLIES_TO[$r['applies_to']] ?? $r['applies_to']) ?> · <?= e(Labels::BASES[$r['charge_basis']] ?? $r['charge_basis']) ?></td>
            <td><?= e(Labels::money((int) $r['amount_cents'])) ?></td>
            <td><?= e((string) $r['free_units']) ?></td>
            <td><?= e($r['valid_from'] === null && $r['valid_to'] === null ? 'sempre' : Labels::date($r['valid_from']) . ' → ' . Labels::date($r['valid_to'])) ?></td>
            <td><?= e($r['apartment_id'] === null ? 'tutti' : ($apartmentNames[(int) $r['apartment_id']] ?? '?')) ?></td>
            <td><?= (int) $r['is_active'] === 1 ? 'sì' : 'no' ?></td>
            <td class="row-actions">
                <a href="<?= e(url('/admin/listino/regole/' . $r['id'])) ?>">Modifica</a>
                <form method="post" action="<?= e(url('/admin/listino/regole/' . $r['id'] . '/elimina')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button-secondary">Elimina</button>
                </form>
            </td>
        </tr>
<?php endforeach; ?>
<?php if ($rules === []): ?>
        <tr><td colspan="8">Nessuna regola.</td></tr>
<?php endif; ?>
    </tbody>
</table>
</div>
