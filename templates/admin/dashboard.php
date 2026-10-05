<?php
/**
 * @var int $pendingRequests
 * @var int $apartments
 */
?>
<h1>Area amministrativa</h1>

<dl class="stats">
    <div>
        <dt>Richieste in attesa</dt>
        <dd><?= e((string) $pendingRequests) ?></dd>
    </div>
    <div>
        <dt>Appartamenti</dt>
        <dd><?= e((string) $apartments) ?></dd>
    </div>
</dl>

<p>Le funzioni di gestione (richieste, prenotazioni, blocchi, prezzi) verranno aggiunte nelle prossime fasi.</p>
