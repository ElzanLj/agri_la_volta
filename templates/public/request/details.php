<?php
/**
 * @var array<string, string> $values
 * @var array<string, string> $errors messages by field
 * @var array<string, mixed> $apartment
 * @var string $token
 * @var string $honeypot
 */
use App\Domain\StayDates;
use App\Http\View;
use App\Site\Format;
use App\Site\Locale;

$lang = Locale::current();
$hidden = array_intersect_key($values, array_flip(['check_in', 'check_out', 'adults', 'children', 'pets'])) + ['apartment' => (string) $apartment['slug']];
$stay = StayDates::fromStrings($values['check_in'], $values['check_out']);
?>
<h1><?= e(t('flow.details.title')) ?></h1>
<?= View::capture('public/request/_steps', ['step' => 3]) ?>

<p class="stay-summary">
    <strong><?= e((string) $apartment['name']) ?></strong> —
    <?= e(Format::date($stay->checkIn, $lang)) ?> → <?= e(Format::date($stay->checkOut, $lang)) ?>
    (<?= e(Format::nights($stay, $lang)) ?>)
    <a href="<?= e(lurl('request.apartments', [], array_intersect_key($values, array_flip(['check_in', 'check_out', 'adults', 'children', 'pets'])))) ?>"><?= e(t('form.back')) ?></a>
</p>
<?= View::capture('public/request/_errors', ['errors' => $errors]) ?>

<p class="hint"><?= e(t('form.required_except_notes')) ?></p>
<form method="post" action="<?= e(lurl('request.summary')) ?>" class="form-wide" novalidate>
    <input type="hidden" name="_form" value="<?= e($token) ?>">
<?php foreach ($hidden as $name => $value): ?>
    <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
<?php endforeach; ?>
    <div class="hp" aria-hidden="true">
        <label for="f-<?= e($honeypot) ?>"><?= e(t('form.honeypot')) ?></label>
        <input type="text" id="f-<?= e($honeypot) ?>" name="<?= e($honeypot) ?>" value="" tabindex="-1" autocomplete="off">
    </div>

    <div class="field-row">
        <div class="field">
            <label for="f-first_name"><?= e(t('form.first_name')) ?></label>
            <input type="text" id="f-first_name" name="first_name" value="<?= e(old($values, 'first_name')) ?>" maxlength="100" autocomplete="given-name" required<?= invalid_attrs($errors, 'first_name', 'err-first_name') ?>>
            <?= field_error($errors, 'first_name', 'err-first_name') ?>
        </div>
        <div class="field">
            <label for="f-last_name"><?= e(t('form.last_name')) ?></label>
            <input type="text" id="f-last_name" name="last_name" value="<?= e(old($values, 'last_name')) ?>" maxlength="100" autocomplete="family-name" required<?= invalid_attrs($errors, 'last_name', 'err-last_name') ?>>
            <?= field_error($errors, 'last_name', 'err-last_name') ?>
        </div>
    </div>
    <div class="field-row">
        <div class="field">
            <label for="f-email"><?= e(t('form.email')) ?></label>
            <input type="email" id="f-email" name="email" value="<?= e(old($values, 'email')) ?>" maxlength="254" autocomplete="email" required<?= invalid_attrs($errors, 'email', 'err-email') ?>>
            <?= field_error($errors, 'email', 'err-email') ?>
        </div>
        <div class="field">
            <label for="f-phone"><?= e(t('form.phone')) ?></label>
            <input type="tel" id="f-phone" name="phone" value="<?= e(old($values, 'phone')) ?>" maxlength="40" autocomplete="tel" required<?= invalid_attrs($errors, 'phone', 'err-phone') ?>>
            <?= field_error($errors, 'phone', 'err-phone') ?>
        </div>
    </div>
    <div class="field">
        <label for="f-notes"><?= e(t('form.notes')) ?></label>
        <textarea id="f-notes" name="notes" rows="4" maxlength="2000"<?= invalid_attrs($errors, 'notes', 'err-notes') ?>><?= e(old($values, 'notes')) ?></textarea>
        <?= field_error($errors, 'notes', 'err-notes') ?>
    </div>

    <button type="submit" class="button"><?= e(t('form.continue')) ?></button>
</form>
