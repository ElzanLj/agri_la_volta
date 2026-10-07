<?php
/**
 * @var array{pending: int, upcoming: int, blocks: int, apartments: int} $counts
 * @var array<string, int> $mail
 * @var int $failedLogins failed admin logins in the last 24 hours
 */
$mailProblems = ($mail['failed'] ?? 0) + ($mail['pending'] ?? 0) + ($mail['sending'] ?? 0);
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
        <dt>Email non inviate o in coda</dt>
        <dd><a href="<?= e(url('/admin/email' . (($mail['failed'] ?? 0) > 0 ? '?stato=failed' : '')) ) ?>"><?= e((string) $mailProblems) ?></a></dd>
    </div>
    <div>
        <dt>Appartamenti</dt>
        <dd><a href="<?= e(url('/admin/appartamenti')) ?>"><?= e((string) $counts['apartments']) ?></a></dd>
    </div>
    <div>
        <dt>Accessi falliti nelle ultime 24 ore</dt>
        <dd><?= e((string) $failedLogins) ?></dd>
    </div>
</dl>
<?php if ($failedLogins >= 10): ?>
<p class="notice" role="note">Ci sono stati molti tentativi di accesso falliti: se non sei stato tu, cambia la password.</p>
<?php endif; ?>

<p><a class="button" href="<?= e(url('/admin/prenotazioni/nuova')) ?>">Nuova prenotazione manuale</a></p>
