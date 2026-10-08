<?php
/**
 * @var array<string, string> $errors messages by field
 * @var array{username: string, this_login: ?string, previous_login: ?string} $info
 * @var int $failedRecent failed password checks in the last 24 hours
 * @var int $minLength
 */

use App\Http\Admin\Labels;
?>
<h1>Account</h1>

<h2>Accessi</h2>
<dl class="details">
    <dt>Nome utente</dt>
    <dd><?= e($info['username']) ?></dd>
    <dt>Ultimo accesso riuscito prima di questo</dt>
    <dd><?= $info['previous_login'] === null ? 'nessuno (è il primo accesso registrato)' : e(Labels::dateTime($info['previous_login'])) ?></dd>
    <dt>Questo accesso</dt>
    <dd><?= $info['this_login'] === null ? '—' : e(Labels::dateTime($info['this_login'])) ?></dd>
    <dt>Tentativi di accesso falliti nelle ultime 24 ore</dt>
    <dd><?= e((string) $failedRecent) ?></dd>
</dl>
<p class="hint">Se l'ultimo accesso non è tuo, o i tentativi falliti sono molti, cambia la password e premi «Esci da tutti i dispositivi».</p>

<h2>Cambia la password</h2>
<form method="post" action="<?= e(url('/admin/account/password')) ?>" class="form-narrow" autocomplete="off">
    <?= csrf_field() ?>
    <div class="field">
        <label for="current_password">Password attuale</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required<?= invalid_attrs($errors, 'current_password', 'err-current_password') ?>>
        <?= field_error($errors, 'current_password', 'err-current_password') ?>
    </div>
    <div class="field">
        <label for="new_password">Nuova password</label>
        <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="<?= (int) $minLength ?>" required aria-describedby="new_password-hint<?= isset($errors['new_password']) ? ' err-new_password' : '' ?>"<?= isset($errors['new_password']) ? ' aria-invalid="true"' : '' ?>>
        <p class="hint" id="new_password-hint">Almeno <?= (int) $minLength ?> caratteri. Non può essere una password comune (per esempio «Agriturismo2026!») né contenere il nome utente o il nome dell'agriturismo. Una frase lunga va benissimo.</p>
        <?= field_error($errors, 'new_password', 'err-new_password') ?>
    </div>
    <div class="field">
        <label for="new_password_confirm">Ripeti la nuova password</label>
        <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" minlength="<?= (int) $minLength ?>" required<?= invalid_attrs($errors, 'new_password_confirm', 'err-new_password_confirm') ?>>
        <?= field_error($errors, 'new_password_confirm', 'err-new_password_confirm') ?>
    </div>
    <button type="submit" class="button">Cambia la password</button>
    <p class="hint">Dopo il cambio, gli altri dispositivi collegati vengono disconnessi. Questa pagina resta aperta.</p>
</form>

<h2>Esci da tutti i dispositivi</h2>
<p>Se hai perso il telefono o hai mostrato la password a qualcuno: chiude tutte le <strong>altre</strong> sessioni senza cambiare la password. Ti viene chiesta la password attuale.</p>
<form method="post" action="<?= e(url('/admin/account/esci-ovunque')) ?>" class="form-narrow" autocomplete="off">
    <?= csrf_field() ?>
    <div class="field">
        <label for="close_current_password">Password attuale</label>
        <input type="password" id="close_current_password" name="close_current_password" autocomplete="current-password" required<?= invalid_attrs($errors, 'close_current_password', 'err-close_current_password') ?>>
        <?= field_error($errors, 'close_current_password', 'err-close_current_password') ?>
    </div>
    <button type="submit" class="button button-secondary">Esci da tutti gli altri dispositivi</button>
</form>

<h2>Password dimenticata?</h2>
<p>Non esiste un recupero via email. Se non ricordi più la password, serve una persona che possa preparare un comando sul proprio computer e importarlo nel database (anche con phpMyAdmin, senza SSH). La procedura è descritta in <code>docs/COMMANDS.md</code>, sezione «Amministratore».</p>
