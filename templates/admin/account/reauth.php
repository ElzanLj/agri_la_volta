<?php
/**
 * Asks the admin for the password again before a sensitive action.
 *
 * @var string $target internal admin path to return to after the confirmation
 * @var string|null $error
 * @var int $minutes how long a confirmation lasts
 */
?>
<h1>Conferma la tua password</h1>

<p>Questa azione è delicata: per continuare scrivi di nuovo la tua password. La conferma vale <?= (int) $minutes ?> minuti.</p>

<?php if ($error !== null): ?>
<p class="alert alert-error" id="reauth-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/conferma-password')) ?>" class="form-narrow" autocomplete="off"<?= $error !== null ? ' aria-describedby="reauth-error"' : '' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="to" value="<?= e($target) ?>">
    <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="button">Conferma e continua</button>
    <a href="<?= e(url('/admin')) ?>">Annulla</a>
</form>
