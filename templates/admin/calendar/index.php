<?php
/**
 * @var string $month YYYY-MM
 * @var string $monthStart
 * @var list<int> $days
 * @var list<array<string, mixed>> $apartments
 * @var array<int, array<int, array{type: string, id: int, label: string}>> $cells
 * @var string $prev
 * @var string $next
 * @var bool $invalidMonth
 */

$monthNames = ['01' => 'gennaio', '02' => 'febbraio', '03' => 'marzo', '04' => 'aprile', '05' => 'maggio', '06' => 'giugno',
    '07' => 'luglio', '08' => 'agosto', '09' => 'settembre', '10' => 'ottobre', '11' => 'novembre', '12' => 'dicembre'];
[$year, $mm] = explode('-', $month);
?>
<h1>Calendario</h1>

<?php if ($invalidMonth): ?>
<p class="alert alert-error" role="alert">Mese non valido: usa il formato AAAA-MM.</p>
<?php endif; ?>

<p>
    <a href="<?= e(url('/admin/calendario?mese=' . $prev)) ?>">← mese precedente</a>
    · <strong><?= e($monthNames[$mm] . ' ' . $year) ?></strong> ·
    <a href="<?= e(url('/admin/calendario?mese=' . $next)) ?>">mese successivo →</a>
</p>
<p>Ogni casella è la notte che inizia in quel giorno. <strong>P</strong> = prenotato, <strong>B</strong> = bloccato, vuoto = libero.</p>

<div class="table-wrap">
<table class="calendar">
    <caption class="visually-hidden">Occupazione di <?= e($monthNames[$mm] . ' ' . $year) ?></caption>
    <thead>
        <tr>
            <th scope="col">Appartamento</th>
<?php foreach ($days as $d): ?>
            <th scope="col"><?= e((string) $d) ?></th>
<?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
<?php foreach ($apartments as $a): ?>
        <tr>
            <th scope="row"><?= e($a['name']) ?></th>
<?php foreach ($days as $d): ?>
<?php $cell = $cells[(int) $a['id']][$d] ?? null; ?>
<?php if ($cell === null): ?>
            <td></td>
<?php elseif ($cell['type'] === 'booking'): ?>
            <td class="cell-booking"><a href="<?= e(url('/admin/prenotazioni/' . $cell['id'])) ?>" title="<?= e($cell['label']) ?>" aria-label="Prenotazione n. <?= e((string) $cell['id']) ?>, <?= e($cell['label']) ?>">P</a></td>
<?php else: ?>
            <td class="cell-block"><a href="<?= e(url('/admin/blocchi')) ?>" title="<?= e($cell['label']) ?>" aria-label="Blocco n. <?= e((string) $cell['id']) ?><?= $cell['label'] !== '' ? ', ' . e($cell['label']) : '' ?>">B</a></td>
<?php endif; ?>
<?php endforeach; ?>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
</div>
