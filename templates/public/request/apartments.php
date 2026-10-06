<?php
/**
 * @var array<string, string> $values
 * @var \App\Domain\StayDates $stay
 * @var \App\Domain\GuestCounts $guests
 * @var list<array{slug: string, name: string, max_guests: ?int, summary: array{lines: list<array<string, string>>, total: ?string, note: ?string}, problem: ?string}> $options
 */
use App\Http\View;
use App\Site\Format;
use App\Site\Locale;

$lang = Locale::current();
$query = array_intersect_key($values, array_flip(['check_in', 'check_out', 'adults', 'children', 'pets']));
?>
<h1><?= e(t('flow.apartments.title')) ?></h1>
<?= View::capture('public/request/_steps', ['step' => 2]) ?>

<p class="stay-summary">
    <?= e(t('flow.stay_line', [
        'in' => Format::date($stay->checkIn, $lang),
        'out' => Format::date($stay->checkOut, $lang),
        'nights' => Format::nights($stay, $lang),
        'guests' => Format::guests($guests, $lang),
    ])) ?>
    <a href="<?= e(lurl('request', [], $query)) ?>"><?= e(t('form.change_search')) ?></a>
</p>

<?php
$contacts = \App\Site\Contacts::current();
$whatsappFor = static fn (?string $apartmentName): ?string => $contacts->whatsappLink(\App\Support\WhatsApp::businessMessage(
    $lang, $apartmentName, $stay->checkIn, $stay->checkOut, $guests->adults, $guests->children,
));
?>
<?php if ($options === []): ?>
<p class="notice"><?= e(t('flow.apartments.none')) ?></p>
<p><a class="button" href="<?= e(lurl('request', [], $query)) ?>"><?= e(t('form.change_search')) ?></a>
   <a class="button button-secondary" href="<?= e(lurl('contact')) ?>"><?= e(t('contact.title')) ?></a>
<?php if ($whatsappFor(null) !== null): ?>
   <a class="button button-secondary" href="<?= e($whatsappFor(null)) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('flow.whatsapp')) ?></a>
<?php endif; ?>
</p>
<?php else: ?>
<ul class="option-list">
<?php foreach ($options as $option): ?>
    <li class="option">
        <h2><?= e($option['name']) ?></h2>
<?php if ($option['problem'] !== null): ?>
        <p class="notice"><?= e($option['problem']) ?></p>
<?php else: ?>
        <p class="price">
<?php if ($option['summary']['total'] !== null): ?>
            <?= e(t('flow.price_total')) ?>: <strong><?= e($option['summary']['total']) ?></strong>
<?php else: ?>
            <strong><?= e((string) $option['summary']['note']) ?></strong>
<?php endif; ?>
        </p>
        <form method="get" action="<?= e(lurl('request.details')) ?>">
<?php foreach ($query + ['apartment' => $option['slug']] as $name => $value): ?>
            <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
<?php endforeach; ?>
            <button type="submit" class="button"><?= e(t('form.choose', ['name' => $option['name']])) ?></button>
        </form>
<?php endif; ?>
        <p><a href="<?= e(lurl('apartment', ['slug' => $option['slug']])) ?>"><?= e(t('apartments.discover', ['name' => $option['name']])) ?></a>
<?php if ($whatsappFor($option['name']) !== null): ?>
           · <a href="<?= e($whatsappFor($option['name'])) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('flow.whatsapp')) ?></a>
<?php endif; ?>
        </p>
    </li>
<?php endforeach; ?>
</ul>
<p class="muted"><?= e(t('flow.price_note')) ?></p>
<?php endif; ?>
