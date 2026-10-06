<?php
/**
 * @var array<string, string> $values
 * @var array<string, string> $errors messages by field
 * @var string $today
 */
use App\Http\View;
?>
<h1><?= e(t('request.title')) ?></h1>
<?= View::capture('public/request/_steps', ['step' => 1]) ?>
<p><?= e(t('request.intro')) ?></p>
<?= View::capture('public/request/_errors', ['errors' => $errors]) ?>

<form method="get" action="<?= e(lurl('request.apartments')) ?>" class="form-wide" novalidate>
    <div class="field-row">
        <div class="field">
            <label for="f-check_in"><?= e(t('form.check_in')) ?></label>
            <input type="date" id="f-check_in" name="check_in" value="<?= e($values['check_in'] ?? '') ?>" min="<?= e($today) ?>" required autocomplete="off"<?= invalid_attrs($errors, 'check_in', 'err-check_in') ?>>
            <?= field_error($errors, 'check_in', 'err-check_in') ?>
        </div>
        <div class="field">
            <label for="f-check_out"><?= e(t('form.check_out')) ?></label>
            <input type="date" id="f-check_out" name="check_out" value="<?= e($values['check_out'] ?? '') ?>" min="<?= e($today) ?>" required autocomplete="off"<?= invalid_attrs($errors, 'check_out', 'err-check_out') ?>>
            <?= field_error($errors, 'check_out', 'err-check_out') ?>
        </div>
    </div>

    <fieldset>
        <legend><?= e(t('form.guests')) ?></legend>
        <div class="field-row">
            <div class="field">
                <label for="f-adults"><?= e(t('form.adults')) ?></label>
                <input type="number" id="f-adults" name="adults" value="<?= e($values['adults'] ?? '') ?>" min="1" max="20" inputmode="numeric" required<?= invalid_attrs($errors, 'adults', 'err-adults') ?>>
                <?= field_error($errors, 'adults', 'err-adults') ?>
            </div>
            <div class="field">
                <label for="f-children"><?= e(t('form.children')) ?></label>
                <input type="number" id="f-children" name="children" value="<?= e($values['children'] ?? '') ?>" min="0" max="20" inputmode="numeric" placeholder="0"<?= invalid_attrs($errors, 'children', 'err-children') ?>>
                <?= field_error($errors, 'children', 'err-children') ?>
            </div>
            <div class="field">
                <label for="f-pets"><?= e(t('form.pets')) ?></label>
                <input type="number" id="f-pets" name="pets" value="<?= e($values['pets'] ?? '') ?>" min="0" max="10" inputmode="numeric" placeholder="0"<?= invalid_attrs($errors, 'pets', 'err-pets') ?>>
                <?= field_error($errors, 'pets', 'err-pets') ?>
            </div>
        </div>
    </fieldset>

    <button type="submit" class="button"><?= e(t('form.search')) ?></button>
</form>
