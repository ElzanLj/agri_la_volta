<?php
/** @var list<array<string, mixed>> $rows */
?>
<h1>Appartamenti</h1>

<div class="table-wrap">
<table>
    <caption class="visually-hidden">Elenco appartamenti</caption>
    <thead>
        <tr>
            <th scope="col">Nome</th>
            <th scope="col">Indirizzo web</th>
            <th scope="col">Attivo</th>
            <th scope="col">Richieste online</th>
            <th scope="col">Gestione</th>
            <th scope="col">Capienza</th>
            <th scope="col"><span class="visually-hidden">Azioni</span></th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['name']) ?></td>
            <td><?= e($r['slug']) ?></td>
            <td><?= (int) $r['is_active'] === 1 ? 'sì' : 'no' ?></td>
            <td><?= (int) $r['accepts_online_requests'] === 1 ? 'sì' : 'no' ?></td>
            <td><?= $r['management_mode'] === 'agency' ? 'agenzia' . ($r['managing_agency'] ? ' (' . e($r['managing_agency']) . ')' : '') : 'diretta' ?></td>
            <td><?= e($r['max_guests'] === null ? 'da indicare' : (string) $r['max_guests']) ?></td>
            <td><a href="<?= e(url('/admin/appartamenti/' . $r['id'])) ?>">Modifica</a></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
</div>
