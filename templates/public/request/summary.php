<?php
/**
 * @var array<string, string> $values
 * @var array<string, string> $errors messages by field
 * @var array<string, mixed> $apartment
 * @var \App\Domain\StayDates $stay
 * @var \App\Domain\GuestCounts $guests
 * @var array{lines: list<array{label: string, detail: string, amount: string}>, total: ?string, note: ?string} $quote
 * @var string $token
 * @var string $honeypot
 */
use App\Http\View;
use App\Site\Format;
use App\Site\Locale;

$lang = Locale::current();
$fields = ['check_in', 'check_out', 'adults', 'children', 'pets', 'apartment', 'first_name', 'last_name', 'email', 'phone', 'notes'];
?>
<h1><?= e(t('flow.summary.title')) ?></h1>
<?= View::capture('public/request/_steps', ['step' => 4]) ?>
<?= View::capture('public/request/_errors', ['errors' => $errors]) ?>

<dl class="details">
    <dt><?= e(t('summary.apartment')) ?></dt>
    <dd><?= e((string) $apartment['name']) ?></dd>
    <dt><?= e(t('summary.dates')) ?></dt>
    <dd><?= e(Format::date($stay->checkIn, $lang)) ?> → <?= e(Format::date($stay->checkOut, $lang)) ?> (<?= e(Format::nights($stay, $lang)) ?>)</dd>
    <dt><?= e(t('form.guests')) ?></dt>
    <dd><?= e(Format::guests($guests, $lang)) ?></dd>
    <dt><?= e(t('summary.contact')) ?></dt>
    <dd><?= e($values['first_name'] . ' ' . $values['last_name']) ?><br><?= e($values['email']) ?><br><?= e($values['phone']) ?></dd>
<?php if ($values['notes'] !== ''): ?>
    <dt><?= e(t('form.notes')) ?></dt>
    <dd><?= nl2br(e($values['notes'])) ?></dd>
<?php endif; ?>
</dl>

<h2><?= e(t('summary.price')) ?></h2>
<?php if ($quote['lines'] !== []): ?>
<div class="table-wrap">
    <table>
        <tbody>
<?php foreach ($quote['lines'] as $line): ?>
            <tr><th scope="row"><?= e($line['label']) ?><br><span class="muted"><?= e($line['detail']) ?></span></th><td><?= e($line['amount']) ?></td></tr>
<?php endforeach; ?>
        </tbody>
<?php if ($quote['total'] !== null): ?>
        <tfoot><tr><th scope="row"><?= e(t('summary.total')) ?></th><td><strong><?= e($quote['total']) ?></strong></td></tr></tfoot>
<?php endif; ?>
    </table>
</div>
<?php endif; ?>
<?php if ($quote['total'] === null): ?>
<p><strong><?= e((string) $quote['note']) ?></strong> — <?= e(t('summary.unquoted')) ?></p>
<?php endif; ?>
<p class="muted"><?= e(t('flow.price_note')) ?></p>

<form method="post" action="<?= e(lurl('request.submit')) ?>" class="form-wide" novalidate>
    <input type="hidden" name="_form" value="<?= e($token) ?>">
<?php foreach ($fields as $name): ?>
    <input type="hidden" name="<?= e($name) ?>" value="<?= e($values[$name] ?? '') ?>">
<?php endforeach; ?>
    <div class="hp" aria-hidden="true">
        <label for="f-<?= e($honeypot) ?>"><?= e(t('form.honeypot')) ?></label>
        <input type="text" id="f-<?= e($honeypot) ?>" name="<?= e($honeypot) ?>" value="" tabindex="-1" autocomplete="off">
    </div>

    <div class="field field-check">
        <input type="checkbox" id="f-privacy_accepted" name="privacy_accepted" value="1" required<?= invalid_attrs($errors, 'privacy_accepted', 'err-privacy_accepted') ?>>
        <label for="f-privacy_accepted"><?= e(t('form.privacy_before')) ?> <a href="<?= e(lurl('privacy')) ?>" target="_blank" rel="noopener"><?= e(t('form.privacy_link')) ?></a> <?= e(t('form.privacy_after')) ?></label>
        <?= field_error($errors, 'privacy_accepted', 'err-privacy_accepted') ?>
    </div>
    <p class="muted"><?= e(t('flow.not_a_booking')) ?></p>

    <button type="submit" class="button"><?= e(t('form.send')) ?></button>
</form>

<form method="post" action="<?= e(lurl('request.details')) ?>" class="inline-form">
<?php foreach ($fields as $name): ?>
    <input type="hidden" name="<?= e($name) ?>" value="<?= e($values[$name] ?? '') ?>">
<?php endforeach; ?>
    <button type="submit" class="button button-secondary"><?= e(t('form.edit_data')) ?></button>
</form>
