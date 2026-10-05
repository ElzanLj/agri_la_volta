<?php
/** @var array{pending: int, upcoming: int, blocks: int, apartments: int} $counts */
?>
<h1>Area amministrativa</h1>

<dl class="stats">
    <div>
        <dt>Richieste in attesa</dt>
        <dd><a href="<?= e(url('/admin/richieste?stato=pending')) ?>"><?= e((string) $counts['pending']) ?></a></dd>
    </div>
    <div>
        <dt>Prenotazioni in corso o future</dt>
        <dd><a href="<?= e(url('/admin/prenotazioni?stato=confirmed')) ?>"><?= e((string) $counts['upcoming']) ?></a></dd>
    </div>
    <div>
        <dt>Blocchi attivi o futuri</dt>
        <dd><a href="<?= e(url('/admin/blocchi')) ?>"><?= e((string) $counts['blocks']) ?></a></dd>
    </div>
    <div>
        <dt>Appartamenti</dt>
        <dd><a href="<?= e(url('/admin/appartamenti')) ?>"><?= e((string) $counts['apartments']) ?></a></dd>
    </div>
</dl>

<p><a class="button" href="<?= e(url('/admin/prenotazioni/nuova')) ?>">Nuova prenotazione manuale</a></p>
