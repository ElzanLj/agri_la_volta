<?php
/**
 * @var string|null $error
 * @var string $username
 */
?>
<h1>Accesso amministratore</h1>

<?php if ($error !== null): ?>
<p class="alert alert-error" id="login-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/login')) ?>" class="form-narrow"<?= $error !== null ? ' aria-describedby="login-error"' : '' ?>>
    <?= csrf_field() ?>
    <div class="field">
        <label for="username">Nome utente</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>"
               autocomplete="username" autocapitalize="none" spellcheck="false" required>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="button">Accedi</button>
</form>
